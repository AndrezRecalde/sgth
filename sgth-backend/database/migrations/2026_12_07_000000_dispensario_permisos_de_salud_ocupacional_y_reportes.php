<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Salud ocupacional, reportes y tableros del Dispensario por permisos
 * (docs/plan-roles-y-accesos.md, PR 17d).
 *
 * Las rutas pedían roles. Pasan a permisos dados a los mismos roles, así que
 * nadie gana ni pierde nada. `gestionar-fichas-sso-med` (el médico),
 * `solicitar-certificacion-medica` y `gestionar-onboarding` (Talento Humano)
 * ya los tenían quienes entraban. Esta migración da los cuatro nuevos:
 *
 * - `ver-tablero-dispensario`: la administración.
 * - `ver-fichas-sso-med`: el médico y la administración.
 * - `ver-certificaciones-medicas`: el médico, la administración, admin-uath
 *   y asistente-uath.
 * - `ver-reportes-dispensario`: el médico, el odontólogo, la enfermera y la
 *   administración.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const OTORGADOS = [
        'ver-tablero-dispensario'     => ['admin-dispensario'],
        'ver-fichas-sso-med'          => ['medico', 'admin-dispensario'],
        'ver-certificaciones-medicas' => ['medico', 'admin-dispensario', 'admin-uath', 'asistente-uath'],
        'ver-reportes-dispensario'    => ['medico', 'odontologo', 'enfermera', 'admin-dispensario'],
    ];

    public function up(): void
    {
        foreach (self::OTORGADOS as $permiso => $roles) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);

            foreach ($roles as $nombreRol) {
                Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                    ?->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones a roles y a personas caen por cascada.
        Permission::whereIn('name', array_keys(self::OTORGADOS))->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
