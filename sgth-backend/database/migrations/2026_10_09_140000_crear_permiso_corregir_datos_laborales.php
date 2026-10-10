<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `corregir-datos-laborales`: corregir a mano la fecha de ingreso a la
 * institución (diseño de Acciones de Personal, 8.3; fase 1.5).
 *
 * Desde la fase 1.5 esa fecha sale de la historia de vínculos, y el régimen
 * laboral, del contrato vigente: la ficha deja de editarlos. Queda esta puerta
 * para los errores de la carga inicial, y el cambio queda en la auditoría de la
 * ficha (ServidorObserver). Lo tiene admin-uath, que hasta ahora los editaba
 * sin permiso propio.
 *
 * En una migración y no solo en `RolPermisoSeeder`, por lo mismo que los demás:
 * el seeder sincroniza y se llevaría los ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const PERMISO = 'corregir-datos-laborales';

    public function up(): void
    {
        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        Role::where('name', 'admin-uath')->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
