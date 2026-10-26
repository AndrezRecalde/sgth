<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El permiso del buscador de servidores (docs/plan-roles-y-accesos.md, PR 2).
 *
 * El buscador de los formularios pedía el listado del Expediente, y
 * `ServidorPolicy::verAny` solo lo sirve a Talento Humano: el personal de TIC
 * recibía 403 al registrar un ticket, entregar un equipo o prestarlo, y el
 * auditor no podía filtrar por servidor.
 *
 * - `admin-uath` y `asistente-uath`, que ya buscaban por el Expediente.
 * - `tecnico-dtic`, `jefe-tic` y `admin-ti`, para los formularios de TIC.
 * - `auditor`, para los filtros de Accidentes, EPP, Consolidado y Períodos.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se asigna nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const PERMISO = 'buscar-servidores';

    private const ROLES = [
        'admin-uath', 'asistente-uath', 'tecnico-dtic', 'jefe-tic', 'admin-ti', 'auditor',
    ];

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
