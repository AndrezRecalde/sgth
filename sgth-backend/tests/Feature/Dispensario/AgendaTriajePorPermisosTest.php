<?php

/*
| Agenda, triaje, enfermería e historias del Dispensario por permisos
| (docs/plan-roles-y-accesos.md, PR 17b).
|
| Las rutas pedían roles. Pasan a permisos dados a los mismos roles, así que
| la matriz de quién entra no cambia; lo nuevo es que un permiso suelto basta.
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

test('cada rol entra a la agenda, el triaje y las historias como antes', function (string $metodo, string $ruta, array $entran) {
    foreach (['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia', 'servidor', 'admin-uath'] as $rol) {
        $respuesta = $this->actingAs(($this->conRol)($rol), 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}");

        in_array($rol, $entran, true)
            ? expect($respuesta->status())->not->toBe(403, "{$rol} debería entrar a {$metodo} {$ruta}")
            : expect($respuesta->status())->toBe(403, "{$rol} no debería entrar a {$metodo} {$ruta}");
    }
})->with([
    'turnos del día'      => ['GET', 'agenda/turnos-del-dia', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'agenda'              => ['GET', 'agenda', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'devolver a la cola'  => ['PATCH', 'agenda/999999/devolver-a-la-cola', ['medico', 'odontologo']],
    'registrar triaje'    => ['POST', 'agenda/999999/triaje', ['enfermera', 'admin-dispensario']],
    'leer triaje'         => ['GET', 'agenda/999999/triaje', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'pacientes'           => ['GET', 'pacientes/buscar?cedula=0800000000', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'atenciones'          => ['GET', 'atenciones-enfermeria', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'registrar atención'  => ['POST', 'atenciones-enfermeria', ['enfermera', 'admin-dispensario']],
    'historias'           => ['GET', 'historias-clinicas', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'anular alergia'      => ['PATCH', 'historias-clinicas/999999/alergias/999999/anular', ['medico', 'odontologo']],
    'disponibilidad'      => ['GET', 'disponibilidad/personal', ['medico', 'odontologo', 'enfermera', 'admin-dispensario']],
    'mi disponibilidad'   => ['GET', 'disponibilidad/mi-estado', ['medico', 'odontologo']],
    'triaje previo FEMO'  => ['GET', 'solicitudes-certificacion/pendientes-triaje', ['enfermera', 'admin-dispensario']],
]);

test('un permiso suelto basta, sin el rol', function () {
    $triaje = User::factory()->create();
    $triaje->givePermissionTo('registrar-triaje');

    $this->actingAs($triaje, 'sanctum')->getJson('/api/v1/dispensario/solicitudes-certificacion/pendientes-triaje')->assertOk();
    $this->actingAs($triaje, 'sanctum')->getJson('/api/v1/dispensario/historias-clinicas')->assertForbidden();
});

test('la migracion da los permisos de agenda y triaje a los mismos roles que el seeder', function () {
    $migracion = require database_path('migrations/2026_12_05_000000_dispensario_permisos_de_agenda_y_triaje.php');
    $permisos = ['registrar-triaje', 'gestionar-agenda'];

    $titulares = fn () => collect($permisos)->mapWithKeys(fn (string $p) => [
        $p => Role::where('guard_name', 'sanctum')
            ->whereHas('permissions', fn ($q) => $q->where('name', $p))
            ->orderBy('name')->pluck('name')->all(),
    ])->all();

    $delSeeder = $titulares();
    expect($delSeeder['registrar-triaje'])->toBe(['admin-dispensario', 'enfermera']);

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::findByName('enfermera', 'sanctum')->hasPermissionTo('gestionar-agenda'))->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($titulares())->toBe($delSeeder);
});
