<?php

/*
| Salud ocupacional, reportes y tableros del Dispensario por permisos
| (docs/plan-roles-y-accesos.md, PR 17d).
|
| Con esta parte el Dispensario queda por permisos, salvo «Mi jornada»: la
| jornada es la de quien atiende según quién es, y eso es identidad.
*/

use App\Models\User;
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
});

test('cada rol entra a salud ocupacional, reportes y tableros como antes', function (string $metodo, string $ruta, array $entran) {
    // `medico-ocupacional` va aquí solo para ver lo que suma; en la vida real
    // se da junto con `medico` (MedicoOcupacionalTest).
    foreach (['medico', 'medico-ocupacional', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia', 'admin-uath', 'asistente-uath', 'servidor'] as $rol) {
        $respuesta = $this->actingAs(($this->conRol)($rol), 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}");

        in_array($rol, $entran, true)
            ? expect($respuesta->status())->not->toBe(403, "{$rol} debería entrar a {$metodo} {$ruta}")
            : expect($respuesta->status())->toBe(403, "{$rol} no debería entrar a {$metodo} {$ruta}");
    }
})->with([
    'tablero del Dispensario' => ['GET', 'dashboard/kpis', ['admin-dispensario']],
    'reportes'                => ['GET', 'reportes', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'mi jornada'              => ['GET', 'mi-jornada', ['medico', 'odontologo', 'enfermera']],
    'solicitudes'             => ['GET', 'solicitudes-certificacion', ['medico-ocupacional', 'admin-dispensario', 'admin-uath', 'asistente-uath']],
    'cobertura'               => ['GET', 'certificaciones/cobertura', ['medico-ocupacional', 'admin-dispensario', 'admin-uath', 'asistente-uath']],
    'iniciar la evaluación'   => ['PATCH', 'solicitudes-certificacion/999999/iniciar', ['medico-ocupacional']],
    'solicitud en lote'       => ['POST', 'solicitudes-certificacion/lote', ['admin-uath', 'asistente-uath']],
    'confirmar incorporación' => ['POST', 'solicitudes-certificacion/999999/confirmar-incorporacion', ['admin-uath']],
    'tablero SSO'             => ['GET', 'salud-ocupacional/tablero', ['medico-ocupacional', 'admin-dispensario']],
    'fichas FEMO'             => ['GET', 'fichas-sso', ['medico-ocupacional', 'admin-dispensario']],
    'llenar una ficha'        => ['POST', 'fichas-sso', ['medico-ocupacional']],
]);

test('un permiso suelto basta, sin el rol', function () {
    $tablero = User::factory()->create();
    $tablero->givePermissionTo('ver-tablero-dispensario');

    $this->actingAs($tablero, 'sanctum')->getJson('/api/v1/dispensario/dashboard/kpis')->assertOk();
    $this->actingAs($tablero, 'sanctum')->getJson('/api/v1/dispensario/fichas-sso')->assertForbidden();
});

test('la migracion da los permisos de salud ocupacional y reportes a los mismos roles que el seeder', function () {
    $migracion = require database_path('migrations/2026_12_07_000000_dispensario_permisos_de_salud_ocupacional_y_reportes.php');
    $permisos = ['ver-tablero-dispensario', 'ver-fichas-sso-med', 'ver-certificaciones-medicas', 'ver-reportes-dispensario'];

    $titulares = fn () => collect($permisos)->mapWithKeys(fn (string $p) => [
        $p => Role::where('guard_name', 'sanctum')
            ->whereHas('permissions', fn ($q) => $q->where('name', $p))
            ->orderBy('name')->pluck('name')->all(),
    ])->all();

    $delSeeder = $titulares();
    expect($delSeeder['ver-certificaciones-medicas'])->toBe(['admin-dispensario', 'admin-uath', 'asistente-uath', 'medico-ocupacional']);

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migracion->up();
    $migracion->up(); // idempotente
    // La 2026_12_11 pasó la salud ocupacional de `medico` a
    // `medico-ocupacional`; sin ella, esta deja a todo médico con las fichas.
    (require database_path('migrations/2026_12_11_000000_crear_rol_medico_ocupacional.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($titulares())->toBe($delSeeder);
});
