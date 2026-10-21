<?php

/*
| Fase 5.1 del diseño de Acciones de Personal (4.4 y 9.4; TH N12): las
| licencias con remuneración salen de los «motivos» de la solicitud de
| vacaciones. Se registran en Asistencia con su causa, su tope y su respaldo,
| sin acción de personal y sin descontar vacaciones, y ocupan sus días.
|
| Hoy es el lunes 12 de octubre de 2026.
*/

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\VacacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección Licencias']);
    $this->servidor = servidorLicencia($this->unidad, '0802704171', TipoNombramiento::PERMANENTE);

    $this->uath = usuarioLicencia('admin-uath');
    $this->actingAs($this->uath, 'sanctum');

    $this->registrar = fn (array $extra = []) => $this->post('/api/v1/asistencia/licencias', [
        'servidor_id'  => $this->servidor->id,
        'causa'        => 'matrimonio',
        'fecha_inicio' => '2026-10-19',
        'fecha_fin'    => '2026-10-21',
        'archivo'      => UploadedFile::fake()->create('acta.pdf', 80, 'application/pdf'),
        ...$extra,
    ], ['Accept' => 'application/json']);
});

function usuarioLicencia(string $rol, ?Servidor $servidor = null): User
{
    $u = User::create([
        'email' => uniqid('lic').'@example.com', 'usuario_ti' => uniqid('lc'),
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $u->assignRole($rol);

    return $u;
}

function servidorLicencia($unidad, string $cedula, TipoNombramiento $nombramiento, ?string $hasta = null): Servidor
{
    $servidor = Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Servidor', 'apellido' => 'Licencia'.substr($cedula, -3),
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => $nombramiento->value,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id'                => $servidor->puesto_id,
        'fecha_inicio'             => '2020-01-02',
        'fecha_fin'                => $hasta,
        'estado'                   => 'vigente',
    ]);

    return $servidor;
}

// ── Registrarla ─────────────────────────────────────────────────

test('se registra con su respaldo, pendiente, con su tope y su base legal', function () {
    ($this->registrar)()
        ->assertCreated()
        ->assertJsonPath('datos.folio', 'LIC-2026-00001')
        ->assertJsonPath('datos.estado', 'pendiente')
        ->assertJsonPath('datos.dias', 3)
        ->assertJsonPath('datos.tope', 3)
        ->assertJsonPath('datos.base_legal', 'LOSEP, Art. 27 j')
        ->assertJsonPath('datos.documento_nombre', 'acta.pdf')
        ->assertJsonMissingPath('datos.documento_ruta');

    $licencia = Licencia::sole();
    Storage::disk('local')->assertExists($licencia->documento_ruta);

    $this->get("/api/v1/asistencia/licencias/{$licencia->id}/documento")->assertOk();
});

test('pasado el tope de la causa, no se registra', function () {
    ($this->registrar)(['fecha_fin' => '2026-10-22'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'admite hasta 3 días'));

    // La paternidad, 15 días; por cesárea, 5 más.
    $paternidad = ['causa' => 'paternidad', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-07'];
    ($this->registrar)($paternidad)->assertStatus(422);
    ($this->registrar)([...$paternidad, 'alcance' => 'cesarea_o_multiple'])->assertCreated()->assertJsonPath('datos.tope', 20);
});

test('la calamidad dice de quién, y de eso depende el tope', function () {
    $calamidad = ['causa' => 'calamidad_domestica', 'fecha_inicio' => '2026-11-09', 'fecha_fin' => '2026-11-12'];

    ($this->registrar)($calamidad)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'de quién'));
    ($this->registrar)([...$calamidad, 'alcance' => 'otro_pariente'])->assertStatus(422);
    ($this->registrar)([...$calamidad, 'alcance' => 'pariente_cercano'])->assertCreated();

    // Un caso que no es de esa causa.
    ($this->registrar)(['alcance' => 'prematuro'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no corresponde'));
});

test('la catastrófica dura lo que diga el certificado', function () {
    ($this->registrar)(['causa' => 'enfermedad_catastrofica', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2027-04-30'])
        ->assertCreated()
        ->assertJsonPath('datos.tope', null);
});

test('pide el respaldo en PDF, y la capacitación ya no es una licencia', function () {
    ($this->registrar)(['archivo' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['archivo'], 'errores');

    ($this->registrar)(['causa' => 'capacitacion'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['causa'], 'errores');
});

test('Servicios Profesionales y las autoridades no la tienen, y nadie registra la propia', function () {
    $profesional = servidorLicencia($this->unidad, '0802704172', TipoNombramiento::SERVICIOS_PROFESIONALES, '2026-12-31');
    ($this->registrar)(['servidor_id' => $profesional->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'contrato civil'));

    $prefecto = servidorLicencia($this->unidad, '0802704173', TipoNombramiento::ELECCION_POPULAR, now()->addYear()->toDateString());
    ($this->registrar)(['servidor_id' => $prefecto->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Consejo Provincial'));

    $propio = usuarioLicencia('asistente-uath', $this->servidor);
    $this->actingAs($propio, 'sanctum');
    ($this->registrar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su propia licencia'));
});

// ── Los cruces ──────────────────────────────────────────────────

test('no cabe sobre vacaciones, permisos, otra licencia ni una ausencia de acción de personal', function () {
    $vacacion = Vacacion::create([
        'servidor_id' => $this->servidor->id, 'folio' => 'VAC-2026-00009', 'motivo' => 'vacaciones_anuales',
        'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-23', 'dias_solicitados' => 4,
        'tipo_dias' => 'calendario', 'estado' => 'pendiente',
    ]);
    ($this->registrar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'VAC-2026-00009'));
    $vacacion->update(['estado' => 'anulada']);

    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'folio' => 'PER-2026-00077', 'tipo' => 'personal',
        'fecha' => '2026-10-21', 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        'estado' => EstadoPermiso::ACTIVO->value, 'vence_en' => '2026-10-24 10:00',
    ]);
    ($this->registrar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'PER-2026-00077'));
    $permiso->update(['estado' => EstadoPermiso::ANULADO->value]);

    MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0101',
        'descripcion' => 'Comisión', 'fecha_efectiva' => '2026-10-15',
        'fecha_inicio' => '2026-10-15', 'fecha_fin' => '2026-10-30', 'institucion_destino' => 'MSP',
    ]);
    ($this->registrar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'AP-2026-0101'));
    MovimientoPersonal::query()->update(['estado' => EstadoAccionPersonal::ANULADA->value]);

    ($this->registrar)()->assertCreated();
    ($this->registrar)(['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-21'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'LIC-2026-00001'));
});

test('sobre una licencia no se piden vacaciones, y anulada los libera', function () {
    $id = ($this->registrar)()->assertCreated()->json('datos.id');

    $pedir = fn () => app(VacacionService::class)->solicitar([
        'motivo' => 'vacaciones_anuales', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-22',
    ], $this->servidor->id);

    expect($pedir)->toThrow(ReglaNegocioException::class, 'LIC-2026-00001');

    $this->postJson("/api/v1/asistencia/licencias/{$id}/anular", ['motivo' => 'Fechas equivocadas.'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'anulada');

    // Ya no choca con la licencia: ahora lo que falta es el período.
    expect($pedir)->toThrow(ReglaNegocioException::class, 'período de vacaciones abierto');
});

// ── Quién ───────────────────────────────────────────────────────

test('la registra Talento Humano; Trabajo Social ve solo las que aprueba', function () {
    $this->actingAs(usuarioLicencia('asistente-uath'), 'sanctum');
    ($this->registrar)()->assertCreated();
    ($this->registrar)(['causa' => 'enfermedad', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-20'])->assertCreated();

    $ts = usuarioLicencia('trabajo-social');
    $this->actingAs($ts, 'sanctum');
    ($this->registrar)(['fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-02'])->assertForbidden();
    $this->getJson('/api/v1/asistencia/licencias')
        ->assertOk()
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.causa', 'enfermedad');

    $this->actingAs(usuarioLicencia('recepcion'), 'sanctum');
    $this->getJson('/api/v1/asistencia/licencias')->assertForbidden();
});

test('el catálogo de causas trae su tope y sus casos', function () {
    $causas = collect($this->getJson('/api/v1/asistencia/licencias/causas')->assertOk()->json('datos'))->keyBy('codigo');

    expect($causas->keys()->all())->not->toContain('capacitacion')
        ->and($causas['maternidad']['tope'])->toBe(84)
        ->and($causas['calamidad_domestica']['exige_alcance'])->toBeTrue()
        ->and(collect($causas['paternidad']['alcances'])->pluck('tope', 'codigo')->all())
        ->toBe(['cesarea_o_multiple' => 20, 'prematuro' => 23, 'enfermedad_degenerativa' => 40]);
});

// ── Las vacaciones ──────────────────────────────────────────────

test('la solicitud de vacaciones ya no acepta los motivos de licencia', function () {
    $this->postJson('/api/v1/asistencia/vacaciones', [
        'servidor_id' => $this->servidor->id, 'motivo' => 'matrimonio',
        'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-21', 'dias_solicitados' => 3, 'tipo_dias' => 'calendario',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['motivo'], 'errores')
        ->assertJsonPath('errores.motivo.0', fn (string $m) => str_contains($m, 'Licencias'));
});
