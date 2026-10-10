<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 1.6 del diseño de Acciones de Personal (6.2): registrar no es lo mismo
| que surtir efecto. Lo que rige más tarde queda pendiente de vigencia, y el
| comando diario aplica su efecto el día en que rige.
|
| Las fechas son relativas a hoy: el registro decide con la fecha real.
*/
beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-MOT', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = fn (int $plazas = 5) => Puesto::create([
        'codigo' => 'P-MOT-'.uniqid(), 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => $plazas,
    ]);

    $this->puestoA = ($this->puesto)();

    $this->estados = app(MovimientoPersonalStateService::class);

    $this->hoy    = now()->toDateString();
    $this->manana = now()->addDay()->toDateString();
    $this->enUnMes = now()->addMonth()->toDateString();

    $this->contador = 0;

    $this->servidor = function (?Puesto $puesto = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8400000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Motor'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2015-01-05',
        ]);

        if ($puesto) {
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => 'nombramiento_permanente',
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $puesto->id,
                'fecha_inicio'             => '2015-01-05',
                'estado'                   => 'vigente',
            ]);
            $servidor->update(['puesto_id' => $puesto->id, 'unidad_administrativa_id' => $this->unidad->id]);
        }

        return $servidor->fresh();
    };

    $this->suscrita = fn (Servidor $s, array $datos) => MovimientoPersonal::create([
        'servidor_id' => $s->id,
        'estado'      => EstadoAccionPersonal::SUSCRITA,
        'descripcion' => 'Acción del motor de efectos',
        'requiere_dictamen_medico' => false,
        ...$datos,
    ]);

    $this->cesacion = fn (Servidor $s, string $rige) => ($this->suscrita)($s, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'fecha_efectiva'     => $rige,
    ]);

    $this->ingreso = fn (Servidor $s, string $rige, Puesto $puesto) => ($this->suscrita)($s, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => 'nombramiento_permanente',
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $puesto->id,
        'numero_contrato'             => 'CT-MOT-'.uniqid(),
        'remuneracion_propuesta'      => 1200,
        'fecha_efectiva'              => $rige,
    ]);

    $this->registrar = fn (MovimientoPersonal $m) => $this->estados->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
});

// ── Registrar no es surtir efecto ───────────────────────────────

test('lo que rige hoy surte efecto al registrarse, como siempre', function () {
    $servidor = ($this->servidor)($this->puestoA);

    $registrada = ($this->registrar)(($this->cesacion)($servidor, $this->hoy));

    expect($registrada->efecto_aplicado_en)->not->toBeNull()
        ->and($registrada->pendienteDeVigencia())->toBeFalse()
        ->and($servidor->fresh()->estado)->toBeFalse();
});

test('una cesación que rige el mes próximo no saca hoy al servidor', function () {
    $servidor = ($this->servidor)($this->puestoA);

    $registrada = ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));

    expect($registrada->codigo_registro)->not->toBeNull()
        ->and($registrada->pendienteDeVigencia())->toBeTrue()
        ->and($servidor->fresh()->estado)->toBeTrue()
        ->and($servidor->fresh()->puesto_id)->toBe($this->puestoA->id)
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->value('estado')->value)->toBe('vigente');
});

test('el día en que rige, el comando le aplica el efecto', function () {
    $servidor = ($this->servidor)($this->puestoA);
    $registrada = ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));

    // La víspera no pasa nada.
    $antes = $this->estados->aplicarVigentes(now()->addMonth()->subDay()->toDateString());
    expect($antes['aplicadas'])->toBe([])
        ->and($servidor->fresh()->estado)->toBeTrue();

    $this->artisan('sgth:acciones:aplicar-vigentes', ['--fecha' => $this->enUnMes])
        ->expectsOutputToContain('1 acción(es) surtieron efecto')
        ->assertSuccessful();

    expect($registrada->fresh()->efecto_aplicado_en)->not->toBeNull()
        ->and($servidor->fresh()->estado)->toBeFalse();
});

test('el efecto se aplica una sola vez', function () {
    $servidor = ($this->servidor)($this->puestoA);
    ($this->registrar)(($this->cesacion)($servidor, $this->manana));

    $primera = $this->estados->aplicarVigentes($this->manana);
    $segunda = $this->estados->aplicarVigentes($this->manana);

    expect($primera['aplicadas'])->toHaveCount(1)
        ->and($segunda['aplicadas'])->toBe([]);
});

// ── El ingreso que espera su fecha ──────────────────────────────

test('el ingreso que rige más tarde no crea el contrato hasta su fecha', function () {
    $nuevo = ($this->servidor)();

    ($this->registrar)(($this->ingreso)($nuevo, $this->enUnMes, $this->puestoA));

    expect(ContratoServidor::where('servidor_id', $nuevo->id)->exists())->toBeFalse();

    $this->estados->aplicarVigentes($this->enUnMes);

    expect(ContratoServidor::where('servidor_id', $nuevo->id)->value('fecha_inicio')->toDateString())
        ->toBe($this->enUnMes)
        ->and($nuevo->fresh()->estado)->toBeTrue();
});

test('mientras espera, su plaza ya no se la puede llevar otro', function () {
    $unaPlaza = ($this->puesto)(1);

    ($this->registrar)(($this->ingreso)(($this->servidor)(), $this->enUnMes, $unaPlaza));

    expect(fn () => ($this->registrar)(($this->ingreso)(($this->servidor)(), $this->hoy, $unaPlaza)))
        ->toThrow(ReglaNegocioException::class, 'no tiene plazas disponibles');
});

test('y el día en que rige no compite consigo mismo por la plaza', function () {
    $unaPlaza = ($this->puesto)(1);
    $nuevo = ($this->servidor)();

    ($this->registrar)(($this->ingreso)($nuevo, $this->manana, $unaPlaza));

    expect($this->estados->aplicarVigentes($this->manana)['fallidas'])->toBe([])
        ->and(ContratoServidor::where('servidor_id', $nuevo->id)->exists())->toBeTrue();
});

// ── El «ascenso» ────────────────────────────────────────────────

test('cesación e ingreso del mismo día: se registran los dos y el comando cierra primero', function () {
    $servidor = ($this->servidor)($this->puestoA);
    $puestoB = ($this->puesto)();

    ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));
    // El vínculo sigue abierto, pero su cesación ya está registrada para ese día.
    ($this->registrar)(($this->ingreso)($servidor, $this->enUnMes, $puestoB));

    $resultado = $this->estados->aplicarVigentes($this->enUnMes);

    $vigentes = ContratoServidor::where('servidor_id', $servidor->id)->where('estado', 'vigente')->get();

    expect($resultado['fallidas'])->toBe([])
        ->and($vigentes)->toHaveCount(1)
        ->and($vigentes->first()->puesto_id)->toBe($puestoB->id)
        ->and($servidor->fresh()->estado)->toBeTrue()
        // Sin un día fuera: la antigüedad no se reinicia (fase 1.5).
        ->and($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2015-01-05');
});

test('un ingreso que rige antes que la cesación pendiente no se registra', function () {
    $servidor = ($this->servidor)($this->puestoA);

    ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));

    expect(fn () => ($this->registrar)(($this->ingreso)($servidor, $this->manana, ($this->puesto)())))
        ->toThrow(ReglaNegocioException::class, 'vínculo laboral vigente');
});

test('si el ingreso rige ya, primero se aplica la cesación que ya debía regir', function () {
    $servidor = ($this->servidor)($this->puestoA);

    // Registrada ayer para hoy, y el comando de esta madrugada aún no corrió.
    $cesacion = ($this->registrar)(($this->cesacion)($servidor, $this->manana));
    \Illuminate\Support\Facades\DB::table('movimientos_personal')->where('id', $cesacion->id)
        ->update(['fecha_efectiva' => $this->hoy]);

    ($this->registrar)(($this->ingreso)($servidor, $this->hoy, ($this->puesto)()));

    expect($cesacion->fresh()->efecto_aplicado_en)->not->toBeNull()
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->where('estado', 'vigente')->count())->toBe(1);
});

// ── Lo que falla y lo que se anula ──────────────────────────────

test('lo que no se puede aplicar queda pendiente con su motivo, y lo demás sigue', function () {
    $servidor = ($this->servidor)($this->puestoA);
    $otro = ($this->servidor)($this->puestoA);

    // Dos cesaciones del mismo vínculo para el mismo día: la segunda ya no
    // encuentra qué cerrar.
    $primera = ($this->registrar)(($this->cesacion)($servidor, $this->manana));
    $segunda = ($this->registrar)(($this->cesacion)($servidor, $this->manana));
    $ajena   = ($this->registrar)(($this->cesacion)($otro, $this->manana));

    $resultado = $this->estados->aplicarVigentes($this->manana);

    expect(collect($resultado['aplicadas'])->pluck('id')->all())->toBe([$primera->id, $ajena->id])
        ->and($resultado['fallidas'][0]['id'])->toBe($segunda->id)
        ->and($resultado['fallidas'][0]['motivo'])->toContain('no tiene un vínculo laboral vigente')
        ->and($segunda->fresh()->pendienteDeVigencia())->toBeTrue();
});

test('anular una que espera su fecha no toca el vínculo', function () {
    $servidor = ($this->servidor)($this->puestoA);
    $pendiente = ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));

    $this->estados->transicionar($pendiente, EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Desistió de la renuncia.']);

    expect($servidor->fresh()->estado)->toBeTrue()
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->value('estado')->value)->toBe('vigente')
        // Y ya no surte efecto nunca.
        ->and($this->estados->aplicarVigentes($this->enUnMes)['aplicadas'])->toBe([]);
});

test('la cesación pendiente no se anula si un ingreso posterior ya cuenta con ella', function () {
    $servidor = ($this->servidor)($this->puestoA);
    $cesacion = ($this->registrar)(($this->cesacion)($servidor, $this->enUnMes));
    ($this->registrar)(($this->ingreso)($servidor, $this->enUnMes, ($this->puesto)()));

    expect(fn () => $this->estados->transicionar($cesacion->fresh(), EstadoAccionPersonal::ANULADA, [
        'motivo_anulacion' => 'Se registró por error.',
    ]))->toThrow(ReglaNegocioException::class, 'Anule primero la más reciente');
});

// ── La subrogación ──────────────────────────────────────────────

test('una subrogación que empieza más tarde se activa el día en que empieza', function () {
    $subrogante = ($this->servidor)($this->puestoA);
    $titular = ($this->servidor)($this->puestoA);

    $accion = ($this->suscrita)($subrogante, [
        'tipo_movimiento' => TipoMovimientoPersonal::SUBROGACION->value,
        'fecha_efectiva'  => $this->enUnMes,
        'fecha_inicio'    => $this->enUnMes,
        'fecha_fin'       => now()->addMonths(2)->toDateString(),
        'dictamen_presupuestario_ref' => 'DICT-MOT-1',
    ]);

    $subrogacion = Subrogacion::create([
        'tipo'                     => 'subrogacion',
        'servidor_subrogante_id'   => $subrogante->id,
        'servidor_subrogado_id'    => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puestoA->id,
        'fecha_inicio'             => $this->enUnMes,
        'fecha_fin'                => now()->addMonths(2)->toDateString(),
        'motivo'                   => 'vacaciones',
        'estado'                   => EstadoSubrogacion::PENDIENTE,
        'movimiento_personal_id'   => $accion->id,
        'registrado_por'           => auth()->id(),
    ]);

    ($this->registrar)($accion);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::PENDIENTE);

    $this->estados->aplicarVigentes($this->enUnMes);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);
});

// ── Lo que ve la pantalla ───────────────────────────────────────

test('el detalle dice que la acción está pendiente de vigencia', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $director = User::factory()->create();
    $director->assignRole('admin-uath');

    $pendiente = ($this->registrar)(($this->cesacion)(($this->servidor)($this->puestoA), $this->enUnMes));

    $this->actingAs($director, 'sanctum')
        ->getJson("/api/v1/expediente/movimientos/{$pendiente->id}")
        ->assertOk()
        ->assertJsonPath('datos.pendiente_de_vigencia', true)
        ->assertJsonPath('datos.efecto_aplicado_en', null);
});
