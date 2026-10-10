<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos del trámite de una acción de personal (diseño de Acciones de
 * Personal, 6.3; TH 22): el asistente prepara y notifica, el director
 * suscribe, registra y anula. Hasta aquí lo decidía el rol en la ruta, y el
 * asistente podía todo.
 *
 * En una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Aquí solo se agregan, a los roles que ya
 * existen; en una base nueva los crea el seeder.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const POR_ROL = [
        'admin-uath' => [
            'preparar-accion-personal',
            'suscribir-accion-personal',
            'registrar-accion-personal',
            'notificar-accion-personal',
            'anular-accion-personal',
        ],
        'asistente-uath' => [
            'preparar-accion-personal',
            'notificar-accion-personal',
        ],
    ];

    public function up(): void
    {
        foreach (self::POR_ROL['admin-uath'] as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::POR_ROL as $rol => $permisos) {
            Role::where('name', $rol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::POR_ROL['admin-uath'])
            ->where('guard_name', 'sanctum')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
