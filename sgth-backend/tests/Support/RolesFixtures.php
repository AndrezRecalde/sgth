<?php

use App\Enums\Permiso;
use Database\Seeders\RolPermisoSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los cinco roles del Dispensario con los permisos del Dispensario que les da
 * `RolPermisoSeeder`, sin los permisos base.
 *
 * El Dispensario pasa de `role:` a `permission:` por partes
 * (docs/plan-roles-y-accesos.md, PR 17), y unas 70 pruebas creaban el rol
 * pelado con `Role::firstOrCreate(['name' => 'medico', …])`: con el rol
 * bastaba. Tests/Pest.php llama a esto antes de cada prueba de
 * Feature/Dispensario, así que esas pruebas encuentran el rol ya creado y con
 * sus permisos, igual que en producción, sin tocarlas una por una. Sin los
 * base, para no darles nada que antes no tenían.
 */
function rolesDelDispensario(): void
{
    $porRol = [
        'medico'            => RolPermisoSeeder::PERMISOS_MEDICO,
        'odontologo'        => RolPermisoSeeder::PERMISOS_ODONTOLOGO,
        'enfermera'         => RolPermisoSeeder::PERMISOS_ENFERMERA,
        'admin-dispensario' => RolPermisoSeeder::PERMISOS_ADMIN_DISPENSARIO,
        'auxiliar-farmacia' => RolPermisoSeeder::PERMISOS_AUXILIAR_FARMACIA,
    ];

    foreach ($porRol as $rol => $permisos) {
        $nombres = array_map(fn (Permiso $p) => $p->value, $permisos);

        foreach ($nombres as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum'])->givePermissionTo($nombres);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

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
