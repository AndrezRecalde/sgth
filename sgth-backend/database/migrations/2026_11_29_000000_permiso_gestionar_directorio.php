<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El permiso del directorio telefónico (docs/plan-roles-y-accesos.md, PR 11).
 *
 * Las extensiones se editaban con `role:admin-ti|admin-uath`: Talento Humano
 * mantenía un dato de la central telefónica y el técnico que la configura no
 * podía. Desde aquí las mantiene TIC —`tecnico-dtic`, `jefe-tic` y
 * `admin-ti`— y admin-uath deja de editarlas. Consultar el directorio sigue
 * abierto a todo el personal, ahora desde el Portal.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se asigna nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const PERMISO = 'gestionar-directorio';

    private const ROLES = ['tecnico-dtic', 'jefe-tic', 'admin-ti'];

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
