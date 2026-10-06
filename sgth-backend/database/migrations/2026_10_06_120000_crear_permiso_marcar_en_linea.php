<?php

use Spatie\Permission\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * `marcar-en-linea`: registrar la marcación desde el navegador, con ubicación.
 *
 * Hasta ahora podía marcar en línea cualquiera con `puede_marcar`, desde
 * cualquier lugar. Decidido con el usuario (2026-10-06): solo quien tenga este
 * permiso, que TI asigna persona por persona desde Usuarios a pedido de Talento
 * Humano. Por eso no se le da a ningún rol.
 *
 * Nadie lo pierde al desplegar: en el biométrico nunca se registró una
 * marcación con ubicación, así que la marcación en línea no estaba en uso.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const PERMISO = 'marcar-en-linea';

    public function up(): void
    {
        Permission::firstOrCreate([
            'name'       => self::PERMISO,
            'guard_name' => 'sanctum',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
