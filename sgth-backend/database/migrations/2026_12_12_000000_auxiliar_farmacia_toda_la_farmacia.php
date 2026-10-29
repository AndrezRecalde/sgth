<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El auxiliar de farmacia lleva toda la Farmacia (decisión del 2026-10-29).
 *
 * Desde el PR 13 tenía el inventario y el mostrador: ver, dar de baja y
 * despachar. Suma `gestionar-inventario-med` —el catálogo de medicamentos,
 * los ajustes de stock y las adquisiciones, que hasta aquí eran solo de la
 * administración— y `ver-reportes-dispensario`, con el que ve los dos
 * reportes de Farmacia (existencias y caducidades, y movimiento de
 * medicamentos): su perfil en `AlcanceReporte` no ve ningún otro.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const ROL = 'auxiliar-farmacia';

    private const PERMISOS = ['gestionar-inventario-med', 'ver-reportes-dispensario'];

    public function up(): void
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'sanctum')->first();

        if ($rol) {
            foreach (self::PERMISOS as $nombre) {
                Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
            }

            $rol->givePermissionTo(self::PERMISOS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Solo al rol: los dos permisos los tienen otros roles.
        $rol = Role::where('name', self::ROL)->where('guard_name', 'sanctum')->first();

        foreach (self::PERMISOS as $nombre) {
            $rol?->revokePermissionTo($nombre);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
