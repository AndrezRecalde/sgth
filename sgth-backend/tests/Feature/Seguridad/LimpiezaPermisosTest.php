<?php

/*
| Limpieza de permisos (docs/plan-roles-y-accesos.md, PR 16).
|
| `puede-marcar-online` no lo comprobaba nada y se confundía con
| `marcar-en-linea`, que es el que decide quién marca desde el navegador. Se
| retira del enum, del seeder y de la base.
*/

use App\Enums\Permiso;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
});

test('puede-marcar-online ya no existe en el enum ni lo siembra el seeder', function () {
    expect(Permiso::tryFrom('puede-marcar-online'))->toBeNull()
        ->and(Permission::where('name', 'puede-marcar-online')->exists())->toBeFalse();
});

test('la migracion lo retira y, al volver atras, se lo devuelve a quienes lo tenian', function () {
    $migracion = require database_path('migrations/2026_12_03_000000_retirar_permiso_puede_marcar_online.php');

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['admin-uath', 'asistente-uath', 'servidor'] as $rol) {
        expect(Role::findByName($rol, 'sanctum')->hasPermissionTo('puede-marcar-online'))->toBeTrue("{$rol} lo tenía");
    }

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::where('name', 'puede-marcar-online')->exists())->toBeFalse();
});

test('marcar en linea sigue dependiendo de marcar-en-linea', function () {
    // Lo que decide de verdad, y que no se le da a ningún rol: TI lo asigna
    // persona por persona.
    foreach (Role::where('guard_name', 'sanctum')->get() as $rol) {
        expect($rol->hasPermissionTo('marcar-en-linea'))->toBeFalse("{$rol->name} no debería tenerlo por rol");
    }
});
