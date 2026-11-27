<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `registrar-acto-autoridad`: los actos del Prefecto y del Viceprefecto.
 *
 * Es la decisión D3 del diseño de «Autoridades electas», respondida por el
 * usuario el 2026-11-27: **lo hacen admin-uath y asistente-uath, pero con un
 * permiso propio**. Hasta aquí iban con `preparar-accion-personal`, el mismo
 * de cualquier acción de personal, así que no había forma de quitarle a
 * alguien los actos de autoridad sin quitarle también el resto.
 *
 * Hoy lo tienen los dos roles, igual que antes: nadie gana ni pierde acceso
 * con esta migración. Lo que cambia es que a partir de ahora **se puede
 * separar** desde Usuarios, sin tocar código.
 *
 * Son el inicio del período, la licencia que concede el Consejo, la cesación,
 * la subrogación por ley, la asunción definitiva, las vacaciones del Consejo y
 * la corrección del fin del período.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr
 * el seeder en producción sincroniza los permisos de cada rol y se llevaría
 * los ajustes hechos desde Usuarios. Aquí solo se agrega a estos dos roles, si
 * ya existen; en una base nueva lo crea el seeder, que ya lo incluye.
 */
return new class extends Migration
{
    private const PERMISO = 'registrar-acto-autoridad';

    /** @var list<string> */
    private const ROLES = ['admin-uath', 'asistente-uath'];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $rol) {
            Role::where('name', $rol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
