<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Agenda, triaje, enfermería e historias del Dispensario por permisos
 * (docs/plan-roles-y-accesos.md, PR 17b).
 *
 * Las rutas pedían roles. Pasan a permisos dados a los mismos roles, así que
 * nadie gana ni pierde nada. `ver-agenda-dispensario`, `ver-historia-clinica`
 * y `crear-consulta` ya estaban asignados a los mismos roles que pedían sus
 * rutas. Esta migración da los otros dos:
 *
 * - `registrar-triaje` (nuevo): Enfermería y la administración, que registran
 *   el triaje, los signos vitales previos al FEMO y los servicios.
 * - `gestionar-agenda` (existía sin dueño): los mismos, que llevan la cola de
 *   todos; el médico y el odontólogo operan solo sus turnos.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const PERMISOS = ['registrar-triaje', 'gestionar-agenda'];

    private const ROLES = ['enfermera', 'admin-dispensario'];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);
        }

        foreach (self::ROLES as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISOS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones de `registrar-triaje` caen por cascada.
        Permission::where('name', 'registrar-triaje')->where('guard_name', 'sanctum')->delete();

        foreach (self::ROLES as $nombreRol) {
            $role = Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first();
            if ($role?->hasPermissionTo('gestionar-agenda', 'sanctum')) {
                $role->revokePermissionTo('gestionar-agenda');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
