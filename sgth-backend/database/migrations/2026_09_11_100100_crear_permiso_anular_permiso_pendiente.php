<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `anular-permiso-pendiente`: anular el permiso PENDIENTE de cualquier servidor.
 *
 * Hasta ahora anular pedía `anular-permiso`, que solo tiene admin-uath y que
 * también habilita revertir confirmaciones —devolver saldo de vacaciones—. La
 * ruta, en cambio, dejaba pasar a asistente-uath, que después recibía un 403 de
 * la policy. Decidido con el usuario: anulan un pendiente el propio servidor y
 * Talento Humano (admin-uath y asistente-uath), sin darle al asistente la
 * capacidad de revertir.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Aquí solo se agrega este permiso a estos dos
 * roles, si ya existen; en una base nueva los crea el seeder, que ya lo incluye.
 */
return new class extends Migration
{
    private const PERMISO = 'anular-permiso-pendiente';

    private const ROLES = ['admin-uath', 'asistente-uath'];

    public function up(): void
    {
        $permiso = Permission::firstOrCreate([
            'name'       => self::PERMISO,
            'guard_name' => 'sanctum',
        ]);

        foreach (self::ROLES as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
