<?php

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSumario;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Mail\Disciplinario\SancionParaNominaMail;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La multa y la suspensión llegan a Financiero como acción de personal
 * «Régimen Disciplinario — Sanción disciplinaria», por correo al jefe de la
 * unidad financiera y con el descuento referencial (decisión de TH,
 * 2026-10-04).
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');
    $this->actingAs($this->admin, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'UATH-SN', 'nombre' => 'Talento Humano SN', 'nivel' => 1]);
    $puesto = Puesto::create(['codigo' => 'P-SN', 'unidad_administrativa_id' => $unidad->id, 'plazas' => 10]);

    // Gestión Financiera, con su jefe y la cuenta institucional del jefe.
    $this->financiera = UnidadAdministrativa::create([
        'codigo' => 'FIN-SN', 'nombre' => 'Gestión Financiera SN', 'nivel' => 1,
    ]);
    $this->financiera->forceFill(['es_unidad_financiera' => true])->save();
    $puestoJefe = Puesto::create([
        'codigo' => 'P-FIN-JEFE', 'unidad_administrativa_id' => $this->financiera->id, 'plazas' => 1, 'es_jefe' => true,
    ]);

    $this->contador = 0;
    $this->servidorCon = function (TipoNombramiento $nombramiento, ?Puesto $enPuesto = null, float $rmu = 1212) use ($unidad, $puesto): Servidor {
        $this->contador++;
        $p = $enPuesto ?? $puesto;
        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (6200000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Nomina'.$this->contador,
            'regimen_laboral'           => $nombramiento === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep',
            'puesto_id'                 => $p->id,
            'unidad_administrativa_id'  => $p->unidad_administrativa_id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);
        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $p->unidad_administrativa_id,
            'puesto_id'                => $p->id,
            'fecha_inicio'             => '2018-01-01',
            'remuneracion'             => $rmu,
            'estado'                   => 'vigente',
        ]);

        return $servidor;
    };

    $jefe = ($this->servidorCon)(TipoNombramiento::PERMANENTE, $puestoJefe, 2500);
    User::factory()->create(['servidor_id' => $jefe->id, 'email' => 'jefe.financiero@gadpe.gob.ec']);

    $this->resolver = function (Servidor $servidor, array $sancion) {
        $sumario = Sumario::create([
            'servidor_id'    => $servidor->id,
            'motivo'         => 'Sumario para nómina',
            'estado'         => EstadoSumario::CON_INFORME,
            'fecha_apertura' => '2026-09-01',
            'notificado_sn'  => true,
            'fecha_informe'  => '2026-09-20',
        ]);

        return $this->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", $sancion + [
            'fecha_efectiva' => '2026-10-06',
        ]);
    };
});

test('una multa crea su acción de personal en borrador con el descuento referencial', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->resolver)($servidor, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5])
        ->assertOk()
        ->assertJsonPath('datos.sancion.movimiento_personal.estado', 'borrador')
        ->assertJsonPath('datos.sancion.descuento_referencial.monto', 60.6);

    $accion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();

    expect($accion->tipo_movimiento)->toBe(TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO)
        ->and($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA)
        ->and($accion->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and((float) $accion->remuneracion_origen)->toBe(1212.0)
        ->and($accion->fecha_efectiva->toDateString())->toBe('2026-10-06')
        ->and($accion->descripcion)->toContain('multa del 5 % de la remuneración mensual unificada')
        ->and($accion->descripcion)->toContain('Arts. 42 literal a) y 43');
});

test('una suspensión descuenta la remuneración entre 30 por los días, en días calendario', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->resolver)($servidor, ['tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5])
        ->assertOk()
        ->assertJsonPath('datos.sancion.descuento_referencial.monto', 202)
        ->assertJsonPath('datos.sancion.descuento_referencial.hasta', '2026-10-10');

    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->sole()->descripcion)
        ->toContain('por 5 días, del 06/10/2026 al 10/10/2026')
        ->toContain('Arts. 42 literal b) y 43');
});

test('una amonestación no crea acción de personal', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)($permanente, ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_escrita'])->assertOk();

    expect(MovimientoPersonal::where('servidor_id', $permanente->id)->count())->toBe(0)
        ->and(SancionDisciplinaria::count())->toBe(1);
});

test('a un obrero se le multa con acción de personal, pero no se le suspende', function () {
    // Decisión de TH (2026-10-04): solo la multa. Antes la multa de un obrero
    // se registraba sin acción y no llegaba a Financiero.
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->resolver)($obrero, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5])
        ->assertOk()
        ->assertJsonPath('datos.sancion.descuento_referencial.monto', 60.6);

    $accion = MovimientoPersonal::where('servidor_id', $obrero->id)->sole();
    expect($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA)
        ->and($accion->descripcion)->toContain('Reglamento Interno de Trabajo y al Código del Trabajo')
        ->and($accion->descripcion)->not->toContain('Servicio Público');

    $otro = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    ($this->resolver)($otro, ['tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5])
        ->assertUnprocessable()
        ->assertJsonPath('mensaje', 'A los obreros bajo Código del Trabajo no se les suspende: se les sanciona con '
            .'amonestación o multa. Una falta grave se tramita como visto bueno ante el Inspector del Trabajo.');

    expect(MovimientoPersonal::where('servidor_id', $otro->id)->count())->toBe(0)
        ->and(SancionDisciplinaria::count())->toBe(1);
});

test('al registrarse sale por correo al jefe de Financiero, y su anulación también', function () {
    Mail::fake();
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)($servidor, ['tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5]);
    $accion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();
    $url = "/api/v1/expediente/movimientos/{$accion->id}/transicionar";

    // Suscribir no avisa: el acto todavía no está registrado.
    $this->putJson($url, ['estado' => 'suscrita'])->assertOk()->assertJsonPath('meta', null);
    Mail::assertNothingSent();

    $this->putJson($url, ['estado' => 'registrada'])
        ->assertOk()
        ->assertJsonPath('meta.aviso_financiero', 'enviado');

    Mail::assertSent(SancionParaNominaMail::class, fn (SancionParaNominaMail $m) => $m->hasTo('jefe.financiero@gadpe.gob.ec')
        && ! $m->anulada
        && $m->descuento['monto'] === 202.0
        && count($m->attachments()) === 1);

    $this->putJson($url, ['estado' => 'anulada', 'motivo_anulacion' => 'La apelación revocó la sanción.'])
        ->assertOk()
        ->assertJsonPath('meta.aviso_financiero', 'enviado');

    Mail::assertSent(SancionParaNominaMail::class, fn (SancionParaNominaMail $m) => $m->anulada);
});

test('sin jefe de Financiero la acción se registra igual y la pantalla lo sabe', function () {
    Mail::fake();
    $this->financiera->forceFill(['es_unidad_financiera' => false])->save();

    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)($servidor, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5]);
    $accion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();
    $url = "/api/v1/expediente/movimientos/{$accion->id}/transicionar";

    $this->putJson($url, ['estado' => 'suscrita'])->assertOk();
    $this->putJson($url, ['estado' => 'registrada'])
        ->assertOk()
        ->assertJsonPath('meta.aviso_financiero', 'sin_destinatario');

    expect($accion->fresh()->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
    Mail::assertNothingSent();
});
