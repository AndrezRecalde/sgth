<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Consultas, exámenes, odontograma, certificados de reposo y CIE-10 del
 * Dispensario por permisos (docs/plan-roles-y-accesos.md, PR 17c).
 *
 * Las rutas pedían roles. Pasan a permisos dados a los mismos roles, así que
 * nadie gana ni pierde nada. `crear-consulta` y `supervisar-dispensario` ya
 * los tenían el médico, el odontólogo y la administración. Esta migración da
 * los tres nuevos:
 *
 * - `ver-examenes`: el médico, el odontólogo y la administración.
 * - `gestionar-catalogo-examenes`: la administración.
 * - `registrar-odontograma`: el odontólogo.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const OTORGADOS = [
        'ver-examenes'                => ['medico', 'odontologo', 'admin-dispensario'],
        'gestionar-catalogo-examenes' => ['admin-dispensario'],
        'registrar-odontograma'       => ['odontologo'],
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
