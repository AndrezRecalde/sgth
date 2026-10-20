<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El SGTH no calcula la nómina (decisión de Talento Humano y Financiero,
 * 2026-10-20): es informativo, y Financiero paga en su sistema. Se retiran los
 * permisos de lo que se fue —ver, procesar y cerrar la nómina, y el traspaso
 * al ERP—, que ya no guardan nada. Las novedades para Financiero se quedan, con
 * los suyos.
 *
 * `ver-rol-pago-propio` sigue hasta que se borren las tablas: lo dan tres
 * migraciones de roles, y sus pruebas de paridad con el seeder lo esperan.
 */
return new class extends Migration
{
    /** Cada permiso y los roles que lo tenían en el seeder. */
    private const PERMISOS = [
        'ver-nomina-unidad'   => ['admin-uath', 'asistente-uath', 'maxima-autoridad', 'director', 'jefe-unidad', 'auditor'],
        'ver-nomina-todas'    => ['admin-uath', 'asistente-uath', 'maxima-autoridad', 'auditor'],
        'procesar-nomina'     => ['admin-uath', 'asistente-uath'],
        'cerrar-nomina'       => ['admin-uath', 'maxima-autoridad'],
        'generar-handoff-erp' => ['admin-uath'],
    ];

    public function up(): void
    {
        // Quitarlo de los roles y de los usuarios lo hace la base, en cascada.
        Permission::whereIn('name', array_keys(self::PERMISOS))->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $nombre => $roles) {
            $permiso = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);

            Role::whereIn('name', $roles)->where('guard_name', 'sanctum')->get()
                ->each(fn (Role $rol) => $rol->givePermissionTo($permiso));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
