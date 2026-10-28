<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Farmacia del Dispensario por permisos (docs/plan-roles-y-accesos.md, PR 17a).
 *
 * Las rutas de inventario, recetas, despacho y adquisiciones pedían roles.
 * Pasan a permisos, dados a los mismos roles que entraban, así que nadie gana
 * ni pierde nada:
 *
 * - `ver-inventario-med` y `ver-recetas` (nuevos): todo el Dispensario y el
 *   auxiliar de farmacia.
 * - `gestionar-inventario-med` (existía sin dueño): la administración, que es
 *   quien llevaba el catálogo, el ajuste de stock y las adquisiciones.
 * - `supervisar-dispensario` (nuevo): la administración, que anula una receta
 *   que emitió otro.
 *
 * `emitir-receta` y `despachar-medicamento` ya estaban asignados a los
 * mismos roles que pedían sus rutas.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios.
 */
return new class extends Migration
{
    private const OTORGADOS = [
        'ver-inventario-med'       => ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia'],
        'ver-recetas'              => ['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia'],
        'gestionar-inventario-med' => ['admin-dispensario'],
        'supervisar-dispensario'   => ['admin-dispensario'],
    ];

    /** Los que crea esta migración; `gestionar-inventario-med` ya existía. */
    private const NUEVOS = ['ver-inventario-med', 'ver-recetas', 'supervisar-dispensario'];

    public function up(): void
    {
        foreach (self::OTORGADOS as $permiso => $roles) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);

            foreach ($roles as $nombreRol) {
                Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                    ?->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones de los nuevos caen por cascada con el permiso.
        Permission::whereIn('name', self::NUEVOS)->where('guard_name', 'sanctum')->delete();

        $admin = Role::where('name', 'admin-dispensario')->where('guard_name', 'sanctum')->first();
        if ($admin?->hasPermissionTo('gestionar-inventario-med', 'sanctum')) {
            $admin->revokePermissionTo('gestionar-inventario-med');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
