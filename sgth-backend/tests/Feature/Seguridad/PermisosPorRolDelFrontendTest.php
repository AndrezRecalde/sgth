<?php

/*
| Los permisos de cada rol que usan las pruebas del menú del frontend.
|
| Las pruebas de Vitest (`sgth-frontend/src/config/nav.test.ts`) arman el menú
| de cada rol con `sgth-frontend/src/test/permisos-por-rol.json`. Esto vigila
| que el archivo sea lo que siembra `RolPermisoSeeder`: si un rol gana o pierde
| un permiso y el archivo no se entera, el frontend seguiría probando un menú
| que ya no es el de nadie (docs/plan-roles-y-accesos.md, PR 9).
|
| Para regenerarlo tras cambiar el seeder:
|
|   SGTH_REGENERAR_PERMISOS_POR_ROL=1 php vendor/bin/pest --filter=PermisosPorRolDelFrontend
|
| y volver a correr `npm test` en el frontend.
*/

use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('el archivo de permisos por rol del frontend es el que siembra el seeder', function () {
    $this->seed(RolPermisoSeeder::class);

    $sembrado = Role::where('guard_name', 'sanctum')
        ->with('permissions')
        ->orderBy('name')
        ->get()
        ->mapWithKeys(fn (Role $rol) => [
            $rol->name => $rol->permissions->pluck('name')->sort()->values()->all(),
        ])
        ->all();

    $archivo = base_path('../sgth-frontend/src/test/permisos-por-rol.json');

    if (env('SGTH_REGENERAR_PERMISOS_POR_ROL')) {
        file_put_contents(
            $archivo,
            json_encode($sembrado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    expect(file_exists($archivo))->toBeTrue("Falta {$archivo}: regénerelo como dice la cabecera de esta prueba.");
    expect(json_decode(file_get_contents($archivo), true))->toEqual($sembrado);
});
