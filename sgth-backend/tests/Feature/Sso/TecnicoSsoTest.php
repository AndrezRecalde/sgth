<?php

/*
| El rol `tecnico-sso` (docs/plan-roles-y-accesos.md, PR 12).
|
| Lleva Riesgos laborales entero sin ser de Talento Humano. Para eso el prefijo
| `sso` pasó de `role:admin-uath|asistente-uath|auditor` a los permisos de
| SSO, el ausentismo dejó de depender del consolidado de Asistencia
| (`ver-permisos-todos`) y el selector de puestos tiene su propio buscador,
| porque el listado de Estructura pide `ver-estructura`.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->tecnico = ($this->conRol)('tecnico-sso');

    $this->unidad = unidadDePrueba(['nombre' => 'Gestión de Prueba de Seguridad']);
    $this->puesto = puestoDePrueba($this->unidad, 'Inspector de Prueba de Riesgos');

    $this->servidor = Servidor::create([
        'cedula' => '0808777771', 'nombre' => 'Rita', 'apellido' => 'Reposo',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->periodo = [
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ];
});

function permisoDeAusentismo(Servidor $servidor, string $folio, TipoPermiso $tipo): PermisoServidor
{
    return PermisoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo'        => $tipo->value,
        'fecha'       => Carbon::today()->toDateString(),
        'hora_inicio' => '08:00',
        'hora_fin'    => '10:00',
        'estado'      => EstadoPermiso::ACTIVO->value,
        'vence_en'    => now()->addDays(3),
        'folio'       => $folio,
    ]);
}

// ── El rol ────────────────────────────────────────────────────────────────

test('el tecnico de sso lleva riesgos laborales y nada de talento humano', function () {
    expect($this->tecnico->hasAllPermissions([
        'gestionar-sso', 'ver-reportes-sso', 'registrar-accidente', 'buscar-servidores',
    ]))->toBeTrue();

    foreach (['ver-estructura', 'ver-permisos-todos', 'ver-expediente-todos', 'ver-expediente-unidad',
        'gestionar-expediente', 'ver-asistencia-todos', 'generar-reportes'] as $permiso) {
        expect($this->tecnico->can($permiso))->toBeFalse("no debería tener {$permiso}");
    }
});

// ── El prefijo sso ────────────────────────────────────────────────────────

test('el tecnico de sso consulta y gestiona el modulo', function () {
    $periodo = now()->format('Y-m');

    foreach (['riesgos', 'accidentes', 'inspecciones', "dashboard/resumen?periodo={$periodo}", 'factores-riesgo'] as $ruta) {
        $this->actingAs($this->tecnico, 'sanctum')
            ->getJson("/api/v1/sso/{$ruta}")
            ->assertOk();
    }

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson('/api/v1/sso/factores-riesgo', ['nombre' => 'Ruido de prueba', 'categoria' => 'fisico'])
        ->assertCreated();
});

test('quienes ya entraban siguen entrando', function () {
    foreach (['admin-uath', 'asistente-uath', 'auditor'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum')
            ->getJson('/api/v1/sso/riesgos')
            ->assertOk();
    }
});

test('sin los permisos de sso el prefijo responde 403', function () {
    foreach (['servidor', 'director', 'admin-ti', 'medico', 'tecnico-dtic'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum')
            ->getJson('/api/v1/sso/riesgos')
            ->assertForbidden();
    }
});

// ── Ausentismo ────────────────────────────────────────────────────────────

test('el ausentismo solo trae los permisos por enfermedad aunque se pida otro tipo', function () {
    permisoDeAusentismo($this->servidor, 'PER-2026-S0001', TipoPermiso::ENFERMEDAD);
    permisoDeAusentismo($this->servidor, 'PER-2026-S0002', TipoPermiso::PERSONAL);

    $respuesta = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/sso/ausentismo?' . http_build_query([...$this->periodo, 'tipo' => 'personal']))
        ->assertOk();

    expect($respuesta->json('datos.filtros.tipo'))->toBe('enfermedad')
        ->and($respuesta->json('datos.consolidado'))->toHaveCount(1)
        ->and($respuesta->json('datos.consolidado.0.total_permisos'))->toBe(1);
});

test('el ausentismo se exporta en csv', function () {
    permisoDeAusentismo($this->servidor, 'PER-2026-S0003', TipoPermiso::ENFERMEDAD);

    $respuesta = $this->actingAs($this->tecnico, 'sanctum')
        ->get('/api/v1/sso/ausentismo/exportar-excel?' . http_build_query($this->periodo))
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toContain('text/csv')
        ->and($respuesta->streamedContent())->toContain('0808777771');
});

test('el tecnico de sso no abre el consolidado de asistencia', function () {
    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/asistencia/consolidado-permisos?' . http_build_query($this->periodo))
        ->assertForbidden();
});

test('sin los permisos de sso el ausentismo responde 403', function () {
    foreach (['servidor', 'director', 'admin-ti'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum')
            ->getJson('/api/v1/sso/ausentismo?' . http_build_query($this->periodo))
            ->assertForbidden();
    }
});

// ── El buscador de puestos ────────────────────────────────────────────────

test('el tecnico de sso busca puestos sin ver la estructura', function () {
    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/estructura/puestos?search=Inspector')
        ->assertForbidden();

    $encontrados = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/estructura/puestos/buscar?search=inspector riesgos')
        ->assertOk()
        ->json('datos');

    expect($encontrados)->toHaveCount(1)
        ->and($encontrados[0])->toBe([
            'id'                    => $this->puesto->id,
            'regimen_laboral'       => $this->puesto->fresh()->regimen_laboral,
            'cargo'                 => ['nombre' => 'Inspector de Prueba de Riesgos'],
            'unidad_administrativa' => ['nombre' => 'Gestión de Prueba de Seguridad'],
        ]);

    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/estructura/puestos/buscar/{$this->puesto->id}")
        ->assertOk()
        ->assertJsonPath('datos.cargo.nombre', 'Inspector de Prueba de Riesgos')
        ->assertJsonMissingPath('datos.rmu');
});

test('talento humano y el auditor siguen buscando puestos', function () {
    foreach (['admin-uath', 'asistente-uath', 'auditor', 'director'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum')
            ->getJson('/api/v1/estructura/puestos/buscar?search=Inspector')
            ->assertOk()
            ->assertJsonCount(1, 'datos');
    }
});

test('sin estructura ni sso el buscador de puestos responde 403', function () {
    foreach (['servidor', 'tecnico-dtic', 'medico'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum')
            ->getJson('/api/v1/estructura/puestos/buscar?search=Inspector')
            ->assertForbidden();
    }
});

test('el buscador de puestos pide al menos dos letras', function () {
    $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/estructura/puestos/buscar?search=I')
        ->assertUnprocessable();
});

// ── Migración ─────────────────────────────────────────────────────────────

test('la migracion crea el rol como el seeder', function () {
    $permisos = fn () => Role::findByName('tecnico-sso', 'sanctum')
        ->permissions()->pluck('name')->sort()->values()->all();

    $delSeeder = $permisos();

    $this->tecnico->syncRoles([]);

    $migracion = require database_path('migrations/2026_11_30_000000_crear_rol_tecnico_sso.php');

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::where('name', 'tecnico-sso')->exists())->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($permisos())->toBe($delSeeder);
});

test('la migracion no retira el rol mientras alguien lo tenga', function () {
    $migracion = require database_path('migrations/2026_11_30_000000_crear_rol_tecnico_sso.php');

    expect(fn () => $migracion->down())->toThrow(RuntimeException::class, $this->tecnico->usuario_ti);
    expect(Role::where('name', 'tecnico-sso')->exists())->toBeTrue();
});
