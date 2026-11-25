<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `reimprimir-vacacion`: volver a sacar el impreso de una solicitud que ya no
 * está autorizada.
 *
 * El impreso es la hoja que el servidor hace firmar por su jefe y baja a
 * ventanilla; una vez registrada, el papel firmado lo archiva Talento Humano y
 * el servidor ya no lo saca. Reimprimirlo sí hace falta —para responder a una
 * auditoría, por ejemplo—, y es de Talento Humano.
 *
 * Va en un permiso propio y no en `ver-asistencia-todos`, que era de donde
 * colgaba: ese permiso lo tiene también auditoría, que es solo lectura, y el
 * usuario decidió (2026-11-25) que la reimpresión no le corresponde. Tampoco
 * cuelga de `gestionar-vacaciones` ni de `aprobar-vacaciones`, que dejarían
 * fuera a asistente-uath, que es quien atiende la ventanilla.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Aquí solo se agrega este permiso a estos
 * roles, si ya existen; en una base nueva los crea el seeder, que ya lo incluye.
 */
return new class extends Migration
{
    private const PERMISO = 'reimprimir-vacacion';

    private const ROLES = ['admin-uath', 'asistente-uath', 'tecnico-asistencia'];

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
