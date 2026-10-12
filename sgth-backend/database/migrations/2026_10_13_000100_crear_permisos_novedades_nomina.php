<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Las novedades para nómina (fase 7.3a):
 *
 * - `ver-novedades-nomina`: Talento Humano, que las prepara; Financiero, que
 *   las aplica en su sistema; y el auditor.
 * - `entregar-novedades-nomina`: congela el mes y se lo envía a Financiero.
 *   Lo tiene el Director de Talento Humano, como registrar una acción [TH 22].
 *
 * En una migración y no solo en `RolPermisoSeeder`, por lo mismo que los demás:
 * el seeder sincroniza y se llevaría los ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'ver-novedades-nomina'      => ['admin-uath', 'asistente-uath', 'financiero', 'auditor'],
        'entregar-novedades-nomina' => ['admin-uath'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre => $roles) {
            $permiso = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);

            Role::whereIn('name', $roles)->where('guard_name', 'sanctum')->get()
                ->each(fn (Role $rol) => $rol->givePermissionTo($permiso));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISOS))->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
