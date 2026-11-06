<?php

/*
| `director` se funde en `jefe-unidad` (decisión del 2026-11-06): los dos veían
| el mismo menú y solo su unidad.
|
| A diferencia de RolesRetiradosTest, la migración no se detiene si alguien
| tiene el rol: le da `jefe-unidad`, que le muestra lo mismo que ya veía.
*/

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    // Una base de antes de la fusión: el rol existe, con un permiso.
    $this->rolViejo = function (): Role {
        $rol = Role::firstOrCreate(['name' => 'director', 'guard_name' => 'sanctum']);
        $rol->givePermissionTo('ver-dashboard-ejecutivo');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $rol;
    };

    $this->migracion = fn () => require database_path('migrations/2026_12_21_000000_fundir_rol_director_en_jefe_unidad.php');
});

it('el seeder y el enum ya no lo tienen, y jefe-unidad dice que también es del director', function () {
    $valores = array_map(fn (Rol $r) => $r->value, Rol::cases());

    expect($valores)->not->toContain('director')
        ->and(Role::where('name', 'director')->exists())->toBeFalse()
        ->and(Rol::JEFE_UNIDAD->etiqueta())->toBe('Jefe de Unidad o Director');
});

it('la migración pasa a jefe-unidad a quien era director y borra el rol', function () {
    ($this->rolViejo)();
    $director = User::factory()->create();
    $director->assignRole('director');
    $conLosDos = User::factory()->create();
    $conLosDos->assignRole(['director', 'jefe-unidad']);

    ($this->migracion)()->up();

    expect(Role::where('name', 'director')->exists())->toBeFalse()
        ->and($director->fresh()->getRoleNames()->all())->toBe(['jefe-unidad'])
        ->and($conLosDos->fresh()->getRoleNames()->all())->toBe(['jefe-unidad'])
        // Ve lo mismo que veía: su unidad, por el rol nuevo.
        ->and($director->fresh()->can('ver-asistencia-unidad'))->toBeTrue()
        ->and($director->fresh()->can('ver-vacaciones-unidad'))->toBeTrue();
});

it('la migración no hace nada si el rol ya no existe', function () {
    ($this->migracion)()->up();

    expect(Role::where('name', 'director')->exists())->toBeFalse()
        ->and(Role::where('name', 'jefe-unidad')->exists())->toBeTrue();
});

it('la reportería ya no la abre un jefe de unidad', function () {
    $jefe = User::factory()->create();
    $jefe->assignRole('jefe-unidad');

    $this->actingAs($jefe, 'sanctum')
        ->getJson('/api/v1/reporteria/dashboard')
        ->assertForbidden();
});

it('volver atrás recrea el rol con sus permisos', function () {
    ($this->migracion)()->down();

    $rol = Role::findByName('director', 'sanctum');

    expect($rol->hasPermissionTo('ver-asistencia-unidad'))->toBeTrue()
        ->and($rol->hasPermissionTo('ver-dashboard-ejecutivo'))->toBeTrue()
        ->and($rol->hasPermissionTo('crear-permiso'))->toBeTrue();
});
