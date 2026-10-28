<?php

/*
| La Farmacia del Dispensario por permisos (docs/plan-roles-y-accesos.md,
| PR 17a).
|
| Inventario, recetas, despacho y adquisiciones pedían roles. Pasan a
| permisos dados a los mismos roles, así que la matriz de quién entra no
| cambia; lo nuevo es que un permiso suelto, dado desde Usuarios, basta.
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

test('cada rol entra a la farmacia como antes', function (string $metodo, string $ruta, array $entran) {
    foreach (['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia', 'servidor', 'admin-uath'] as $rol) {
        $respuesta = $this->actingAs(($this->conRol)($rol), 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}");

        in_array($rol, $entran, true)
            ? expect($respuesta->status())->not->toBe(403, "{$rol} debería entrar a {$metodo} {$ruta}")
            : expect($respuesta->status())->toBe(403, "{$rol} no debería entrar a {$metodo} {$ruta}");
    }
})->with([
    'inventario'      => ['GET', 'inventario/medicinas', ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
    'kárdex'          => ['GET', 'inventario/medicinas/999999/kardex', ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
    'alta de medicina' => ['POST', 'inventario/medicinas', ['admin-dispensario']],
    'ajuste de stock' => ['POST', 'inventario/medicinas/999999/ajustar-inventario', ['admin-dispensario']],
    'baja'            => ['POST', 'inventario/medicinas/999999/baja', ['enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
    'adquisiciones'   => ['GET', 'adquisiciones', ['admin-dispensario']],
    'recetas'         => ['GET', 'recetas', ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
    'emitir receta'   => ['POST', 'recetas', ['medico', 'odontologo']],
    'despachar'       => ['POST', 'recetas/999999/despachar', ['enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
    'anular receta'   => ['POST', 'recetas/999999/anular', ['medico', 'odontologo', 'admin-dispensario']],
    'personal médico' => ['GET', 'personal-medico', ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia']],
]);

test('un permiso suelto basta, sin el rol', function () {
    // Lo que el paso a permisos deja hacer: darle a una persona solo la
    // consulta del inventario, sin hacerla del Dispensario.
    $consulta = User::factory()->create();
    $consulta->givePermissionTo('ver-inventario-med');

    $this->actingAs($consulta, 'sanctum')->getJson('/api/v1/dispensario/inventario/medicinas')->assertOk();
    $this->actingAs($consulta, 'sanctum')->getJson('/api/v1/dispensario/recetas')->assertForbidden();
    $this->actingAs($consulta, 'sanctum')->getJson('/api/v1/dispensario/adquisiciones')->assertForbidden();
});

test('la migracion da los permisos de farmacia a los mismos roles que el seeder', function () {
    $migracion = require database_path('migrations/2026_12_04_000000_dispensario_permisos_de_farmacia.php');
    $permisos = ['ver-inventario-med', 'ver-recetas', 'gestionar-inventario-med', 'supervisar-dispensario'];

    $titulares = fn () => collect($permisos)->mapWithKeys(fn (string $p) => [
        $p => Role::where('guard_name', 'sanctum')
            ->whereHas('permissions', fn ($q) => $q->where('name', $p))
            ->orderBy('name')->pluck('name')->all(),
    ])->all();

    $delSeeder = $titulares();

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::findByName('admin-dispensario', 'sanctum')->hasPermissionTo('gestionar-inventario-med'))->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($titulares())->toBe($delSeeder);
});
