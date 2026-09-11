<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Talento Humano recibe `confirmar-recepcion` y admin-uath `validar-trabajo-social`.
 *
 * Confirmar, rechazar y validar se autorizaban por rol en la ruta
 * (`role:recepcion|admin-uath|asistente-uath`, `role:trabajo-social|admin-uath`),
 * mientras la matriz de permisos solo le daba `confirmar-recepcion` a Recepción
 * y `validar-trabajo-social` a Trabajo Social. Rechazar, además, pasaba por una
 * policy que el asistente no cumplía: la ruta lo dejaba entrar y recibía un 403.
 *
 * Ahora las tres acciones las decide la policy, con permisos. Para que nadie
 * pierda lo que hoy puede hacer, Talento Humano recibe los permisos que antes le
 * daba el rol. Decidido con el usuario: quien confirma el documento también lo
 * rechaza, asistente-uath incluido.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Solo agrega; en una base nueva lo hace el
 * seeder, que ya los incluye.
 */
return new class extends Migration
{
    private const CONCESIONES = [
        'confirmar-recepcion'    => ['admin-uath', 'asistente-uath'],
        'validar-trabajo-social' => ['admin-uath'],
    ];

    public function up(): void
    {
        foreach (self::CONCESIONES as $nombrePermiso => $roles) {
            $permiso = Permission::firstOrCreate([
                'name'       => $nombrePermiso,
                'guard_name' => 'sanctum',
            ]);

            foreach ($roles as $rol) {
                Role::where('name', $rol)->where('guard_name', 'sanctum')->first()
                    ?->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // El permiso sigue existiendo: Recepción y Trabajo Social lo tenían desde
        // antes. Solo se retira de los roles a los que se lo dio esta migración.
        foreach (self::CONCESIONES as $nombrePermiso => $roles) {
            foreach ($roles as $rol) {
                $role = Role::where('name', $rol)->where('guard_name', 'sanctum')->first();

                if ($role?->hasPermissionTo($nombrePermiso, 'sanctum')) {
                    $role->revokePermissionTo($nombrePermiso);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
