<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retira el permiso `solicitar-cita`.
 *
 * Lo usaba solo `POST /autoservicio/solicitar-cita`, que se retiró el
 * 2026-09-11: nunca funcionó —escribía columnas que `agendas_medicas` no tiene
 * y respondía 500— y ninguna pantalla lo llamaba. El permiso quedó en el enum y
 * en los permisos base del seeder, así que cada rol lo tenía asignado sin que
 * sirviera para nada, y aparecía en la pantalla de Usuarios como algo que se
 * podía conceder.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Borrar el permiso lo quita también de cada rol
 * y de cada usuario que lo tuviera.
 */
return new class extends Migration
{
    private const PERMISO = 'solicitar-cita';

    public function up(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Estaba entre los permisos base: lo tenían todos los roles.
        $permiso = Permission::firstOrCreate([
            'name'       => self::PERMISO,
            'guard_name' => 'sanctum',
        ]);

        Role::where('guard_name', 'sanctum')->get()
            ->each(fn (Role $rol) => $rol->givePermissionTo($permiso));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
