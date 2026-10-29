<?php

/*
| Activar o desactivar una cuenta es de TIC (decisión del 2026-10-29).
|
| admin-uath tenía `activar-usuario` sin pantalla: Usuarios está en TIC y la
| lista le daba 403, pero `toggle-activo` le aceptaba cualquier id —también
| el de una cuenta de TIC— y le cerraba las sesiones.
*/

use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioActivarSoloTic(string $rol): User
{
    $usuario = User::factory()->create(['activo' => true]);
    $usuario->assignRole($rol);

    return $usuario;
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->deTic = usuarioActivarSoloTic('jefe-tic');
});

test('Talento Humano ya no desactiva cuentas', function () {
    $this->actingAs(usuarioActivarSoloTic('admin-uath'), 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$this->deTic->id}/toggle-activo")
        ->assertForbidden();

    expect($this->deTic->fresh()->activo)->toBeTrue();
});

test('TIC sí, desde Usuarios', function () {
    $this->actingAs(usuarioActivarSoloTic('admin-ti'), 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$this->deTic->id}/toggle-activo")
        ->assertOk();

    expect($this->deTic->fresh()->activo)->toBeFalse();
});

test('la migracion deja a admin-uath como el seeder', function () {
    $migracion = require database_path('migrations/2026_12_13_000000_admin_uath_sin_activar_usuario.php');
    $permisos = fn () => Role::findByName('admin-uath', 'sanctum')->permissions()->pluck('name')->sort()->values()->all();
    $delSeeder = $permisos();

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::findByName('admin-uath', 'sanctum')->hasPermissionTo('activar-usuario'))->toBeTrue();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($permisos())->toBe($delSeeder)
        ->and(Role::findByName('admin-ti', 'sanctum')->hasPermissionTo('activar-usuario'))->toBeTrue();
});
