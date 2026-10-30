<?php

/*
| El rol `tecnico-asistencia` (decisión del 2026-10-30).
|
| Asistencia solo la podían llevar admin-uath y asistente-uath, que traen todo
| Talento Humano. Este rol lleva el módulo entero, con aprobar vacaciones y
| revertir confirmaciones, y no abre el expediente de nadie. Validar enfermedad
| y calamidad sigue siendo de Trabajo Social.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

function permisoTecnicoAsistencia(Servidor $servidor, TipoPermiso $tipo, EstadoPermiso $estado = EstadoPermiso::PENDIENTE): PermisoServidor
{
    static $secuencial = 0;
    $secuencial++;

    return PermisoServidor::forceCreate([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'tipo'                     => $tipo->value,
        'fecha'                    => now()->addDay()->format('Y-m-d'),
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '10:00',
        'observacion'              => 'Motivo de la prueba',
        'estado'                   => $estado->value,
        'vence_en'                 => now()->addDays(4),
        'confirmado_en'            => $estado === EstadoPermiso::PENDIENTE ? null : now(),
        'folio'                    => sprintf('PER-2026-%05d', 81000 + $secuencial),
    ]);
}

function vacacionTecnicoAsistencia(Servidor $servidor): Vacacion
{
    return Vacacion::forceCreate([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'motivo'                   => 'vacaciones_anuales',
        'fecha_inicio'             => now()->addWeeks(3)->toDateString(),
        'fecha_fin'                => now()->addWeeks(3)->addDays(2)->toDateString(),
        'fecha_emision'            => now()->toDateString(),
        'dias_solicitados'         => 3,
        'tipo_dias'                => 'habiles',
        'estado'                   => 'pendiente',
        'folio'                    => 'VAC-2026-81001',
    ]);
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    config(['services.biometrico.aprobacion_permisos_desde' => null]);

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('tecnico-asistencia');

    $unidad = unidadDePrueba(['nombre' => 'Dirección de la Asistencia']);

    $this->servidor = Servidor::forceCreate([
        'cedula' => '0808111111', 'nombre' => 'Ana', 'apellido' => 'Asistencia',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
});

// ── El rol ────────────────────────────────────────────────────────────────

test('lleva Asistencia y nada más de Talento Humano', function () {
    expect($this->tecnico->hasAllPermissions(
        array_map(fn ($permiso) => $permiso->value, RolPermisoSeeder::PERMISOS_TECNICO_ASISTENCIA)
    ))->toBeTrue();

    foreach ([
        'ver-expediente-todos', 'gestionar-expediente', 'cargar-documentos', 'ver-estructura',
        'ver-novedades-nomina', 'gestionar-sso', 'ver-sumarios', 'preparar-accion-personal',
        'redactar-comunicados', 'ver-certificaciones-medicas', 'validar-trabajo-social',
    ] as $permiso) {
        expect($this->tecnico->can($permiso))->toBeFalse("no debería tener {$permiso}");
    }
});

test('abre las pantallas de Asistencia', function (string $ruta) {
    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/asistencia/{$ruta}")
        ->assertOk();
})->with([
    'permisos',
    'vacaciones',
    'licencias',
    'certificados-medicos',
    'periodos-vacaciones/excedentes',
    'consolidado-permisos?fecha_inicio=2026-10-01&fecha_fin=2026-10-31',
]);

test('consulta el saldo y los períodos de cualquier servidor', function () {
    $this->actingAs($this->tecnico, 'sanctum');

    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidor->id}")->assertOk();
    $this->getJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$this->servidor->id}/resumen")->assertOk();
});

test('no abre lo demás de Talento Humano', function (string $ruta) {
    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/{$ruta}")
        ->assertForbidden();
})->with([
    'expediente/servidores',
    'estructura/puestos',
    'nomina/novedades?periodo=2026-10',
    'sso/riesgos',
    'admin/usuarios',
]);

test('no abre el expediente del servidor que elige en un formulario', function () {
    $this->actingAs($this->tecnico, 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}")->assertForbidden();

    // Pero sí lo encuentra, con lo justo para elegirlo.
    $this->getJson("/api/v1/expediente/servidores/buscar/{$this->servidor->id}")
        ->assertOk()
        ->assertJsonPath('datos.apellido', 'Asistencia')
        ->assertJsonMissingPath('datos.contrato_vigente');
});

// ── Permisos ──────────────────────────────────────────────────────────────

test('registra un permiso a nombre de otro y confirma el documento', function () {
    expect($this->tecnico->can('crear', [PermisoServidor::class, $this->servidor]))->toBeTrue()
        ->and($this->tecnico->can('dirigirATalentoHumano', PermisoServidor::class))->toBeTrue();

    $permiso = permisoTecnicoAsistencia($this->servidor, TipoPermiso::OFICIAL);

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso->folio}")
        ->assertOk()
        ->assertJsonPath('datos.estado', EstadoPermiso::ACTIVO->value);
});

test('rechaza un documento y anula un pendiente ajeno', function () {
    $this->actingAs($this->tecnico, 'sanctum');

    $this->postJson('/api/v1/asistencia/permisos/'.permisoTecnicoAsistencia($this->servidor, TipoPermiso::OFICIAL)->id.'/rechazar', ['motivo' => 'Llegó sin firma'])
        ->assertOk();

    $this->putJson('/api/v1/asistencia/permisos/'.permisoTecnicoAsistencia($this->servidor, TipoPermiso::OFICIAL)->id.'/anular', ['motivo' => 'Se registró por error'])
        ->assertOk();
});

test('revierte una confirmación', function () {
    $permiso = permisoTecnicoAsistencia($this->servidor, TipoPermiso::OFICIAL, EstadoPermiso::ACTIVO);

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/revertir-confirmacion", ['motivo' => 'Se confirmó por error'])
        ->assertOk()
        ->assertJsonPath('datos.estado', EstadoPermiso::PENDIENTE->value);
});

test('lee el motivo de cualquier permiso, como Talento Humano', function () {
    permisoTecnicoAsistencia($this->servidor, TipoPermiso::ENFERMEDAD);

    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertOk()
        ->assertJsonPath('datos.data.0.observacion', 'Motivo de la prueba');
});

test('aprueba en Sirha7 personal y oficial, pero enfermedad y calamidad son de Trabajo Social', function () {
    expect($this->tecnico->can('aprobarSirha7', permisoTecnicoAsistencia($this->servidor, TipoPermiso::OFICIAL, EstadoPermiso::ACTIVO)))->toBeTrue()
        ->and($this->tecnico->can('aprobarSirha7', permisoTecnicoAsistencia($this->servidor, TipoPermiso::PERSONAL, EstadoPermiso::ACTIVO)))->toBeTrue()
        ->and($this->tecnico->can('aprobarSirha7', permisoTecnicoAsistencia($this->servidor, TipoPermiso::ENFERMEDAD, EstadoPermiso::ACTIVO)))->toBeFalse()
        ->and($this->tecnico->can('aprobarSirha7', permisoTecnicoAsistencia($this->servidor, TipoPermiso::CALAMIDAD, EstadoPermiso::ACTIVO)))->toBeFalse();

    $porEnfermedad = permisoTecnicoAsistencia($this->servidor, TipoPermiso::ENFERMEDAD, EstadoPermiso::ACTIVO);

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$porEnfermedad->id}/validar-ts")
        ->assertForbidden();

    expect($porEnfermedad->fresh()->estado)->toBe(EstadoPermiso::ACTIVO);
});

// ── Vacaciones y licencias ────────────────────────────────────────────────

test('registra vacaciones a nombre de otro y gestiona los períodos', function () {
    expect($this->tecnico->can('crear', [Vacacion::class, $this->servidor]))->toBeTrue()
        ->and($this->tecnico->can('gestionarPeriodos', Vacacion::class))->toBeTrue();
});

test('resuelve una solicitud de vacaciones', function () {
    $vacacion = vacacionTecnicoAsistencia($this->servidor);

    $this->actingAs($this->tecnico, 'sanctum')
        ->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'rechazada'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'rechazada');
});

test('anula una solicitud de vacaciones', function () {
    $vacacion = vacacionTecnicoAsistencia($this->servidor);

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/asistencia/vacaciones/{$vacacion->id}/anular", ['motivo' => 'Se registró por error'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'anulada');
});

// ── Migración ─────────────────────────────────────────────────────────────

test('la migración crea el rol como el seeder', function () {
    $permisos = fn () => Role::findByName('tecnico-asistencia', 'sanctum')
        ->permissions()->pluck('name')->sort()->values()->all();

    $delSeeder = $permisos();

    $this->tecnico->syncRoles([]);

    $migracion = require database_path('migrations/2026_12_14_000000_crear_rol_tecnico_asistencia.php');

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::where('name', 'tecnico-asistencia')->exists())->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($permisos())->toBe($delSeeder);
});

test('la migración no retira el rol mientras alguien lo tenga', function () {
    $migracion = require database_path('migrations/2026_12_14_000000_crear_rol_tecnico_asistencia.php');

    expect(fn () => $migracion->down())->toThrow(RuntimeException::class, $this->tecnico->usuario_ti);
    expect(Role::where('name', 'tecnico-asistencia')->exists())->toBeTrue();
});
