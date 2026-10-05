<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * En Reclutamiento, el analista de la UATH ve y califica (decisión de TH,
 * 2026-10-05). Tenía los mismos permisos que admin-uath —crear, publicar,
 * borrar, declarar ganadores e incorporar—, aunque las rutas no los miraban:
 * bastaba con el rol. Ahora las rutas exigen el permiso.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr
 * el seeder en producción sincroniza los permisos de cada rol y se llevaría
 * los ajustes hechos desde Usuarios. Se quitan del rol; si a algún analista se
 * le dieron directamente desde Usuarios, los conserva.
 */
return new class extends Migration
{
    private const RETIRADOS = ['gestionar-convocatorias', 'gestionar-onboarding'];

    public function up(): void
    {
        $rol = Role::where('name', 'analista-uath')->where('guard_name', 'sanctum')->first();

        // Sin `hasPermissionTo`: lanza si el permiso no existe, como en una
        // base recién migrada que todavía no corrió el seeder.
        foreach (self::RETIRADOS as $permiso) {
            if ($rol?->permissions->contains('name', $permiso)) {
                $rol->revokePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $rol = Role::where('name', 'analista-uath')->where('guard_name', 'sanctum')->first();

        foreach (self::RETIRADOS as $permiso) {
            $rol?->givePermissionTo(Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
