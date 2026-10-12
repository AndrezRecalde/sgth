<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSolicitudAccion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\SolicitudAccionPersonal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.3b del diseño de Acciones de Personal (9.13): las solicitudes del
| servidor desde el portal —renuncia, licencia sin remuneración y jubilación—.
| El servidor la presenta con su respaldo y la retira mientras está pendiente;
| Talento Humano la atiende preparando la acción o la rechaza con el motivo.
| La renuncia va con 15 días de anticipación y, sin respuesta en 15 días, se
| tiene por aceptada (Reglamento a la LOSEP, Art. 102).
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $unidad = UnidadAdministrativa::create(['codigo' => 'SOL-ADM', 'nombre' => 'Dirección Administrativa', 'nivel' => 2]);
    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'plazas' => 5, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Analista Administrativo'])->id,
    ]);

    $this->contador = 0;
    /** Un servidor con su vínculo y su cuenta del portal. */
    $this->servidor = function (TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE) use ($unidad, $puesto): array {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6900000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Solicitante'.$this->contador,
            'regimen_laboral' => $nombramiento->esCodigoTrabajo() ? 'codigo_trabajo' : 'losep',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento->value,
            'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
            'fecha_fin' => $nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES ? '2026-12-31' : null,
        ]);
        $usuario = User::factory()->create(['servidor_id' => $servidor->id]);
        $usuario->assignRole('empleado');

        return [$servidor, $usuario];
    };

    $this->th = User::factory()->create();
    $this->th->assignRole('admin-uath');

    $this->presentar = fn (User $como, array $datos) => $this->actingAs($como, 'sanctum')->post(
        '/api/v1/autoservicio/mis-solicitudes',
        ['archivo' => UploadedFile::fake()->create('respaldo.pdf', 80, 'application/pdf'), ...$datos],
        ['Accept' => 'application/json'],
    );
});

// ── El servidor ─────────────────────────────────────────────────

test('presenta su renuncia con 15 días de anticipación y su carta; una pendiente a la vez', function () {
    [, $usuario] = ($this->servidor)();

    ($this->presentar)($usuario, ['tipo' => 'renuncia', 'fecha_desde' => '2026-10-20'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La renuncia se presenta con 15 días de anticipación (Reglamento a la LOSEP, Art. 102): la fecha más próxima en que puede dejar el puesto es el 27/10/2026.');

    ($this->presentar)($usuario, ['tipo' => 'renuncia', 'fecha_desde' => '2026-10-27', 'motivo' => 'Asumo otro cargo.'])
        ->assertCreated()
        ->assertJsonPath('datos.folio', 'SOL-2026-00001')
        ->assertJsonPath('datos.estado', 'pendiente')
        ->assertJsonPath('datos.puede_retirar', true);

    ($this->presentar)($usuario, ['tipo' => 'renuncia', 'fecha_desde' => '2026-11-30'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya tiene una solicitud de Renuncia pendiente (SOL-2026-00001): retírela si quiere presentar otra.');

    // Sin el respaldo, no.
    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/autoservicio/mis-solicitudes', ['tipo' => 'jubilacion', 'fecha_desde' => '2026-12-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['archivo'], 'errores');

    $this->getJson('/api/v1/autoservicio/mis-solicitudes')->assertOk()->assertJsonCount(1, 'datos');
});

test('la licencia sin remuneración, solo de los permanentes y con su causal', function () {
    [, $provisional] = ($this->servidor)(TipoNombramiento::PROVISIONAL);
    ($this->presentar)($provisional, [
        'tipo' => 'licencia_sin_remuneracion', 'causal' => 'asuntos_particulares',
        'fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30',
    ])->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'no aplica a su nombramiento'));

    [, $permanente] = ($this->servidor)();
    ($this->presentar)($permanente, ['tipo' => 'licencia_sin_remuneracion', 'fecha_desde' => '2026-11-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['causal', 'fecha_hasta'], 'errores');

    ($this->presentar)($permanente, [
        'tipo' => 'licencia_sin_remuneracion', 'causal' => 'asuntos_particulares',
        'fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30',
    ])->assertCreated()->assertJsonPath('datos.causal', 'asuntos_particulares');
});

test('el obrero tiene su propio trámite', function () {
    [, $obrero] = ($this->servidor)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->presentar)($obrero, ['tipo' => 'renuncia', 'fecha_desde' => '2026-11-30'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn ($m) => str_starts_with($m, 'Estas solicitudes son de los servidores de la LOSEP'));
});

test('la retira mientras está pendiente; la de otro no existe para él', function () {
    [, $usuario] = ($this->servidor)();
    [, $otro] = ($this->servidor)();
    $id = ($this->presentar)($usuario, ['tipo' => 'jubilacion', 'fecha_desde' => '2026-12-01'])->assertCreated()->json('datos.id');

    $this->actingAs($otro, 'sanctum')->postJson("/api/v1/autoservicio/mis-solicitudes/{$id}/retirar")->assertNotFound();

    $this->actingAs($usuario, 'sanctum')->postJson("/api/v1/autoservicio/mis-solicitudes/{$id}/retirar")
        ->assertOk()
        ->assertJsonPath('datos.estado', 'retirada');
    $this->postJson("/api/v1/autoservicio/mis-solicitudes/{$id}/retirar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La solicitud está retirada: ya no se retira.');
});

// ── Talento Humano ──────────────────────────────────────────────

test('preparar la acción crea el borrador con la clase, la causal, las fechas y la referencia', function () {
    [$servidor, $usuario] = ($this->servidor)();
    $renuncia = ($this->presentar)($usuario, ['tipo' => 'renuncia', 'fecha_desde' => '2026-11-02', 'motivo' => 'Asumo otro cargo.'])->json('datos.id');
    [$permanente, $usuarioLicencia] = ($this->servidor)();
    $licencia = ($this->presentar)($usuarioLicencia, [
        'tipo' => 'licencia_sin_remuneracion', 'causal' => 'asuntos_particulares',
        'fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30',
    ])->json('datos.id');

    $this->actingAs($this->th, 'sanctum')
        ->postJson("/api/v1/expediente/solicitudes-accion/{$renuncia}/preparar")
        ->assertOk()
        ->assertJsonPath('datos.estado', 'atendida');

    $accion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();
    expect($accion->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::RENUNCIA)
        ->and($accion->fecha_efectiva->toDateString())->toBe('2026-11-02')
        ->and($accion->resolucion_numero)->toBe('Solicitud SOL-2026-00001')
        ->and($accion->descripcion)->toContain('Asumo otro cargo.');
    $this->getJson("/api/v1/expediente/movimientos/{$accion->id}")->assertOk()->assertJsonPath('datos.solicitud_folio', 'SOL-2026-00001');

    $this->postJson("/api/v1/expediente/solicitudes-accion/{$licencia}/preparar")->assertOk();
    $porLicencia = MovimientoPersonal::where('servidor_id', $permanente->id)->sole();
    expect($porLicencia->fecha_inicio->toDateString())->toBe('2026-11-01')
        ->and($porLicencia->fecha_fin->toDateString())->toBe('2026-11-30');

    // Al titular, su solicitud dice atendida, pero no le muestra el borrador.
    $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/autoservicio/mis-solicitudes')
        ->assertOk()
        ->assertJsonPath('datos.0.estado', 'atendida')
        ->assertJsonPath('datos.0.accion', null);

    // Ya atendida, no se prepara otra vez.
    $this->actingAs($this->th, 'sanctum')->postJson("/api/v1/expediente/solicitudes-accion/{$renuncia}/preparar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La solicitud SOL-2026-00001 está atendida.');
});

test('se rechaza con el motivo; la renuncia sin respuesta en 15 días ya no', function () {
    [, $usuario] = ($this->servidor)();
    [, $otro] = ($this->servidor)();

    // Presentada el 1 de septiembre: pasaron más de 15 días.
    $this->travelTo(Carbon::parse('2026-09-01 10:00'));
    $vieja = ($this->presentar)($usuario, ['tipo' => 'renuncia', 'fecha_desde' => '2026-10-30'])->json('datos.id');
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));
    $jubilacion = ($this->presentar)($otro, ['tipo' => 'jubilacion', 'fecha_desde' => '2026-12-01'])->json('datos.id');

    $this->actingAs($this->th, 'sanctum');
    $lista = collect($this->getJson('/api/v1/expediente/solicitudes-accion')->assertOk()->json('datos'));
    expect($lista->firstWhere('id', $vieja)['aceptada_por_el_plazo'])->toBeTrue()
        ->and($lista->firstWhere('id', $jubilacion)['aceptada_por_el_plazo'])->toBeFalse();

    $this->postJson("/api/v1/expediente/solicitudes-accion/{$vieja}/rechazar", ['motivo' => 'No procede ahora.'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'se tiene por aceptada (Reglamento a la LOSEP, Art. 102)'));

    $this->postJson("/api/v1/expediente/solicitudes-accion/{$jubilacion}/rechazar", ['motivo' => 'Falta el certificado actualizado del IESS.'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'rechazada');

    $this->actingAs($otro, 'sanctum')->getJson('/api/v1/autoservicio/mis-solicitudes')
        ->assertJsonPath('datos.0.motivo_rechazo', 'Falta el certificado actualizado del IESS.');
});

test('nadie atiende la suya, y el servidor no entra a la bandeja', function () {
    [$servidor, $usuario] = ($this->servidor)();
    $id = ($this->presentar)($usuario, ['tipo' => 'jubilacion', 'fecha_desde' => '2026-12-01'])->json('datos.id');

    $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/expediente/solicitudes-accion')->assertForbidden();

    // Si el que la presentó trabaja en Talento Humano, tampoco la atiende él.
    $usuario->assignRole('admin-uath');
    $this->actingAs($usuario->fresh(), 'sanctum')
        ->postJson("/api/v1/expediente/solicitudes-accion/{$id}/preparar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'No puede tramitar una acción de personal sobre usted mismo: tiene que hacerlo otra persona de Talento Humano.');

    expect(SolicitudAccionPersonal::find($id)->estado)->toBe(EstadoSolicitudAccion::PENDIENTE);
});
