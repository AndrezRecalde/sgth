<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El Expediente pasa del rol `admin-uath|asistente-uath` a sus permisos
 * (docs/revision-permisos-sin-uso.md, D2, D4 y D5).
 *
 * Los cuatro existían desde el principio y nadie los comprobaba. Ahora las
 * rutas y `ServidorPolicy` los piden, así que esta migración se asegura de que
 * los dos roles de Talento Humano los tengan —`givePermissionTo` no duplica—,
 * y de dejar el acceso como estaba:
 *
 * - `cargar-documentos` lo recibe asistente-uath, que sube documentos desde el
 *   2026-10-03 por decisión de Talento Humano.
 * - `ver-expediente-todos` se le quita al auditor, que lo tenía por el seeder
 *   y nunca entró al Expediente.
 *
 * Quien tenga el permiso como permiso adicional, dado desde Usuarios, lo
 * conserva: ese caso no lo toca esta migración.
 */
return new class extends Migration
{
    private const PERMISOS_TH = [
        'ver-expediente-todos',
        'gestionar-expediente',
        'cargar-documentos',
        'gestionar-cargas-familiares',
    ];

    private const ROLES_TH = ['admin-uath', 'asistente-uath'];

    public function up(): void
    {
        foreach (self::PERMISOS_TH as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);
        }

        foreach (self::ROLES_TH as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISOS_TH);
        }

        Role::where('name', 'auditor')->where('guard_name', 'sanctum')->first()
            ?->revokePermissionTo('ver-expediente-todos');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Los demás ya los tenían los dos roles antes de esta migración.
        Role::where('name', 'asistente-uath')->where('guard_name', 'sanctum')->first()
            ?->revokePermissionTo('cargar-documentos');

        Role::where('name', 'auditor')->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo('ver-expediente-todos');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
