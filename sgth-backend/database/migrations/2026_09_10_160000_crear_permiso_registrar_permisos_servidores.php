<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `registrar-permisos-servidores`: emitir permisos a nombre de cualquier
 * servidor.
 *
 * Hasta ahora el alta de un permiso no comprobaba de quién era: `crear-permiso`
 * está entre los permisos base y cualquier usuario podía registrarle uno a otro
 * servidor —y al confirmarlo, descontarle las horas de su saldo de vacaciones—.
 * Talento Humano sí tiene que poder hacerlo; el resto, solo el propio.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr
 * el seeder en producción sincroniza los permisos de cada rol y se llevaría
 * cualquier ajuste hecho desde la pantalla de usuarios. Aquí solo se agrega
 * este permiso a estos dos roles. En una base recién creada los roles aún no
 * existen: los crea el seeder, que ya lo incluye.
 */
return new class extends Migration
{
    private const PERMISO = 'registrar-permisos-servidores';

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
