<?php

/*
| Se retiran `maxima-autoridad` y `analista-uath` (docs/plan-roles-y-accesos.md,
| PR 7). Ninguno tenía usuarios ni un subsistema donde entrar.
|
| La migración no le quita el rol a nadie en silencio: si alguien todavía lo
| tiene, se detiene y dice quién.
*/

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    // Una base de antes del retiro: los dos roles existen, con un permiso.
    $this->rolesViejos = function (): void {
        foreach (['maxima-autoridad', 'analista-uath'] as $nombre) {
            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum'])
                ->givePermissionTo('ver-expediente-todos');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    };

    $this->migracion = fn () => require database_path('migrations/2026_11_27_000000_retirar_roles_maxima_autoridad_y_analista_uath.php');
});

it('el seeder y el enum ya no los tienen', function () {
    $valores = array_map(fn (Rol $r) => $r->value, Rol::cases());

    expect($valores)->not->toContain('maxima-autoridad')
        ->and($valores)->not->toContain('analista-uath')
        ->and(Role::whereIn('name', ['maxima-autoridad', 'analista-uath'])->exists())->toBeFalse();
});

it('la migración los borra si nadie los tiene', function () {
    ($this->rolesViejos)();

    ($this->migracion)()->up();

    expect(Role::whereIn('name', ['maxima-autoridad', 'analista-uath'])->exists())->toBeFalse()
        // El permiso que tenían sigue ahí, para los roles que lo usan.
        ->and(Permission::where('name', 'ver-expediente-todos')->exists())->toBeTrue();
});

it('la migración se detiene y dice quién todavía tiene el rol', function () {
    ($this->rolesViejos)();
    $prefecto = User::factory()->create(['usuario_ti' => 'prefecto.prueba']);
    $prefecto->assignRole('maxima-autoridad');

    expect(fn () => ($this->migracion)()->up())
        ->toThrow(RuntimeException::class, 'prefecto.prueba (maxima-autoridad)');

    expect(Role::where('name', 'maxima-autoridad')->exists())->toBeTrue()
        ->and(Role::where('name', 'analista-uath')->exists())->toBeTrue();
});

it('volver atrás los recrea con sus permisos', function () {
    ($this->migracion)()->down();

    expect(Role::findByName('analista-uath', 'sanctum')->hasPermissionTo('evaluar-postulantes'))->toBeTrue()
        ->and(Role::findByName('maxima-autoridad', 'sanctum')->hasPermissionTo('ver-dashboard-ejecutivo'))->toBeTrue();
});
