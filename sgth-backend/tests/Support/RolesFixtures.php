<?php

use App\Enums\Permiso;
use Database\Seeders\RolPermisoSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los cinco roles del Dispensario con los permisos del Dispensario que les da
 * `RolPermisoSeeder`, sin los permisos base; y admin-uath y asistente-uath con
 * el único permiso del Dispensario que tienen, `ver-certificaciones-medicas`.
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

    // Talento Humano consulta el seguimiento y la cobertura de las
    // certificaciones médicas, que también son rutas del Dispensario (PR 17d).
    $porRol['admin-uath'] = [Permiso::VER_CERTIFICACIONES_MEDICAS];
    $porRol['asistente-uath'] = [Permiso::VER_CERTIFICACIONES_MEDICAS];

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
 * admin-uath y asistente-uath con los permisos del Expediente que les da
 * `RolPermisoSeeder` (`PERMISOS_EXPEDIENTE_TH`).
 *
 * El Expediente pasó del rol `admin-uath|asistente-uath` a sus permisos el
 * 2026-10-28 (docs/revision-permisos-sin-uso.md, 3.2), y unas 80 pruebas
 * creaban el rol pelado: con el rol bastaba. Tests/Pest.php llama a esto antes
 * de cada prueba de los directorios que abren el Expediente, así que esas
 * pruebas encuentran el rol ya creado y con sus permisos, igual que en
 * producción. Solo los del Expediente, para no darles nada que antes no
 * tenían.
 */
function rolesDeTalentoHumano(): void
{
    $nombres = array_map(fn (Permiso $p) => $p->value, RolPermisoSeeder::PERMISOS_EXPEDIENTE_TH);

    foreach ($nombres as $nombre) {
        Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
    }

    foreach (['admin-uath', 'asistente-uath'] as $rol) {
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
