<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El permiso de registrar la ficha de un servidor nuevo
 * (docs/plan-roles-y-accesos.md, PR 14).
 *
 * `ServidorPolicy::crear` lo decidía por el rol admin-uath, y el botón
 * «Registrar ficha» del Expediente no tenía guarda: asistente-uath, que abre
 * el Expediente, lo veía y recibía 403. Ahora los dos van con este permiso,
 * que recibe admin-uath: nadie gana ni pierde nada.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se asigna nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const PERMISO = 'registrar-ficha-servidor';

    private const ROLES = ['admin-uath'];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        foreach (self::ROLES as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones a roles y los permisos adicionales de cada usuario
        // caen por cascada con el permiso.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
