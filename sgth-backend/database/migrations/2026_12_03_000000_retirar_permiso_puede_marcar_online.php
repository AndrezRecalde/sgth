<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Se retira `puede-marcar-online` (docs/plan-roles-y-accesos.md, PR 16).
 *
 * Ningún código lo comprobaba: quién marca en línea lo deciden el permiso
 * `marcar-en-linea`, que TI da persona por persona desde Usuarios
 * (2026_10_06_120000), y la habilitación del contrato (`puede_marcar`). Con
 * un nombre tan parecido, se lo podía dar a alguien creyendo que lo
 * habilitaba. Lo tenían admin-uath, asistente-uath y servidor, por el seeder;
 * nadie pierde nada.
 *
 * Borrar el permiso se lleva sus asignaciones a roles y a personas, por la
 * cascada de las claves foráneas.
 */
return new class extends Migration
{
    private const PERMISO = 'puede-marcar-online';

    /** Los roles que lo tenían por `RolPermisoSeeder`, para volver atrás. */
    private const ROLES = ['admin-uath', 'asistente-uath', 'servidor'];

    public function up(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        foreach (self::ROLES as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
