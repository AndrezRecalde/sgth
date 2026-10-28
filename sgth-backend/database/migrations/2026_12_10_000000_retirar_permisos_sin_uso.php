<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Se retiran cinco permisos que ningún código comprobaba
 * (docs/revision-permisos-sin-uso.md, D1, D3, D9 y D12):
 *
 * - `ver-distributivo`: quién ocupa cada puesto y con qué RMU ya se ve en
 *   Puestos y cargos con `ver-estructura`, y el distributivo es público por la
 *   LOTAIP.
 * - `ver-expediente-unidad`: la jefatura no tiene pantalla de expedientes. Si
 *   algún día tiene una «Mi equipo», llevará un permiso hecho para lo que esa
 *   pantalla muestre.
 * - `ver-actividades-unidad` y `aprobar-actividades`: las actividades las ve
 *   solo quien las registra, sin revisión del jefe (decisión del 2026-10-12,
 *   #431).
 * - `evaluar-personal-medico`: no hay diseño ni pantalla; si el personal
 *   médico se evalúa, lo hará el módulo de Evaluación del desempeño.
 *
 * Nadie pierde nada. Borrar el permiso se lleva sus asignaciones a roles y a
 * personas, por la cascada de las claves foráneas.
 */
return new class extends Migration
{
    /** Los roles que los tenían por `RolPermisoSeeder`, para volver atrás. */
    private const ROLES_POR_PERMISO = [
        'ver-distributivo'        => ['admin-uath'],
        'ver-expediente-unidad'   => ['admin-uath', 'asistente-uath', 'director', 'jefe-unidad', 'auditor'],
        'ver-actividades-unidad'  => ['admin-uath', 'asistente-uath', 'director', 'jefe-unidad', 'auditor'],
        'aprobar-actividades'     => ['director', 'jefe-unidad'],
        'evaluar-personal-medico' => ['admin-dispensario'],
    ];

    public function up(): void
    {
        Permission::whereIn('name', array_keys(self::ROLES_POR_PERMISO))
            ->where('guard_name', 'sanctum')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::ROLES_POR_PERMISO as $permiso => $roles) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);

            foreach ($roles as $nombreRol) {
                Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                    ?->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
