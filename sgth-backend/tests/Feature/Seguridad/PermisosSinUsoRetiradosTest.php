<?php

use App\Enums\Permiso;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Cinco permisos que ningún código comprobaba se retiraron el 2026-10-28
| (docs/revision-permisos-sin-uso.md, D1, D3, D9 y D12).
*/

const PERMISOS_SIN_USO_RETIRADOS = [
    'ver-distributivo',
    'ver-expediente-unidad',
    'ver-actividades-unidad',
    'aprobar-actividades',
    'evaluar-personal-medico',
];

beforeEach(function () {
    $this->migracion = fn () => require database_path('migrations/2026_12_10_000000_retirar_permisos_sin_uso.php');
});

it('el enum y el seeder ya no los tienen', function () {
    $this->seed(RolPermisoSeeder::class);

    $delEnum = array_map(fn (Permiso $p) => $p->value, Permiso::cases());

    expect(array_intersect(PERMISOS_SIN_USO_RETIRADOS, $delEnum))->toBe([])
        ->and(Permission::whereIn('name', PERMISOS_SIN_USO_RETIRADOS)->exists())->toBeFalse();
});

it('la migración los borra con sus asignaciones, y vuelve atrás', function () {
    // Como estaban: en los roles, y uno dado a una persona desde Usuarios.
    foreach (PERMISOS_SIN_USO_RETIRADOS as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);
    }
    $director = Role::firstOrCreate(['name' => 'director', 'guard_name' => 'sanctum']);
    $director->givePermissionTo(['ver-expediente-unidad', 'ver-actividades-unidad', 'aprobar-actividades']);
    Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum'])
        ->givePermissionTo('evaluar-personal-medico');
    $persona = User::factory()->create();
    $persona->givePermissionTo('ver-distributivo');

    ($this->migracion)()->up();

    expect(Permission::whereIn('name', PERMISOS_SIN_USO_RETIRADOS)->exists())->toBeFalse()
        ->and($director->fresh()->permissions)->toHaveCount(0)
        ->and($persona->fresh()->getDirectPermissions())->toHaveCount(0);

    ($this->migracion)()->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::whereIn('name', PERMISOS_SIN_USO_RETIRADOS)->count())->toBe(5)
        ->and($director->fresh()->hasAllPermissions(['ver-expediente-unidad', 'ver-actividades-unidad', 'aprobar-actividades']))->toBeTrue()
        ->and(Role::findByName('admin-dispensario', 'sanctum')->hasPermissionTo('evaluar-personal-medico'))->toBeTrue();
});
