<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `aprobar-permiso-sirha7`: registrar en Sirha7 un permiso personal u oficial
 * ya confirmado por Recepción (decisión de TH, 2026-10-07).
 *
 * Lo tienen admin-uath y asistente-uath. Enfermedad y calamidad no lo usan:
 * las registra Trabajo Social al validarlas, con `validar-trabajo-social`.
 *
 * En una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Aquí solo se agrega este permiso a estos dos
 * roles, si ya existen; en una base nueva los crea el seeder.
 */
return new class extends Migration
{
    private const PERMISO = 'aprobar-permiso-sirha7';

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
