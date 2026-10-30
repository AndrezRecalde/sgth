<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * admin-uath deja de tener `activar-usuario` (decisión del 2026-10-29).
 *
 * El permiso decide `POST admin/usuarios/{id}/toggle-activo`: activar o
 * desactivar una cuenta, y al desactivarla cerrarle las sesiones. Talento
 * Humano no tenía pantalla para eso —Usuarios está en TIC desde el PR 10 del
 * plan de roles y su lista le da 403—, pero el API se lo aceptaba con
 * cualquier id, también el de una cuenta de TIC. Se encontró revisando las
 * cuentas de prueba por rol. Las cuentas las activa y desactiva TIC.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const ROL = 'admin-uath';

    private const PERMISO = 'activar-usuario';

    public function up(): void
    {
        // Solo al rol: el permiso lo sigue teniendo admin-ti.
        Role::where('name', self::ROL)->where('guard_name', 'sanctum')->first()
            ?->revokePermissionTo(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', self::ROL)->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
