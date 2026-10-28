<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Régimen disciplinario pasa del rol `admin-uath` a sus tres permisos
 * (docs/revision-permisos-sin-uso.md, D8): `ver-sumarios` para leer,
 * `gestionar-sumarios` para abrir y hacer avanzar los trámites, y
 * `registrar-sancion` para resolver un sumario, que impone la sanción.
 *
 * Los tres existían desde el principio, solo en admin-uath, y nadie los
 * comprobaba. Esta migración se asegura de que admin-uath los tenga
 * —`givePermissionTo` no duplica—: nadie gana ni pierde nada.
 */
return new class extends Migration
{
    private const PERMISOS = ['ver-sumarios', 'gestionar-sumarios', 'registrar-sancion'];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);
        }

        Role::where('name', 'admin-uath')->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo(self::PERMISOS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nada que deshacer: admin-uath ya los tenía, y el rol vuelve a
        // decidirlo en las rutas.
    }
};
