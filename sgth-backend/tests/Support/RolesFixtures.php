<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Un rol que registra fichas de servidores, para las pruebas que no siembran
 * `RolPermisoSeeder`.
 *
 * `ServidorPolicy::crear` pedía el rol admin-uath y, desde el PR 14 de
 * docs/plan-roles-y-accesos.md, el permiso `registrar-ficha-servidor`. Con él
 * se decide también quién edita la ficha entera (`UpdateServidorRequest`), así
 * que las pruebas que daban de alta o editaban como «admin-uath» con el rol
 * pelado empezaron a recibir 403 y 422.
 */
function rolQueRegistraFichas(string $rol = 'admin-uath'): Role
{
    $role = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    $role->givePermissionTo(
        Permission::firstOrCreate(['name' => 'registrar-ficha-servidor', 'guard_name' => 'sanctum'])
    );

    return $role;
}
