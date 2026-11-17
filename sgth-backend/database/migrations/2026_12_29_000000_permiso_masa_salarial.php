<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La masa salarial referencial (docs/diseno-masa-salarial.md, PR 1).
 *
 * Permiso nuevo `ver-masa-salarial`. Talento Humano y Financiero respondieron
 * el cuestionario el 2026-11-17 (D2): la ven admin-uath, el auditor y
 * financiero. Asistente-uath no.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se les da nada: lo hace el seeder, que ya lo incluye.
 */
return new class extends Migration
{
    private const PERMISO = 'ver-masa-salarial';

    private const CON_EL_PERMISO = ['admin-uath', 'auditor', 'financiero'];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        foreach (self::CON_EL_PERMISO as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Con la consulta y no con el modelo: borrar el modelo desvincula a sus
        // usuarios, y el guard `sanctum` no tiene proveedor con el que buscarlos.
        // Las asignaciones caen por la clave foránea, también la que se haya
        // dado a mano desde Usuarios.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
