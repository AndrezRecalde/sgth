<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de TIC según el diseño del subsistema
 * (docs/diseno-subsistema-tic.md, 4.4 y decisión D5).
 *
 * - Rol nuevo `jefe-tic`: asigna tickets, ve todos, configura la mesa de ayuda
 *   y da de baja equipos. Hasta ahora eso solo podía hacerlo `admin-ti`, que
 *   es el superadministrador del sistema y no el jefe de la unidad.
 * - Permiso nuevo `registrar-mantenimiento`, para técnico y jefe.
 * - `tecnico-dtic` deja `asignar-ticket` y `ver-tickets-todos`: atiende su
 *   cola y los tickets sin asignar, y repartir el trabajo es del jefe.
 * - `admin-ti` recibe todo lo del jefe, de forma explícita, para que la
 *   pantalla de Usuarios muestre lo que el rol puede hacer.
 * - `auditor` consulta el inventario.
 *
 * Nadie pierde nada en uso: el módulo no tenía pantallas y sus tablas están
 * vacías.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye todo esto.
 */
return new class extends Migration
{
    private const ROL = 'jefe-tic';

    private const PERMISO_NUEVO = 'registrar-mantenimiento';

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-rol-pago-propio', 'ver-asistencia-propia',
        'crear-permiso', 'ver-permisos', 'acceso-autoservicio', 'cambiar-contrasena',
        'ver-historia-clinica-propia', 'crear-ticket', 'ver-ticket-propio',
        'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia',
        'solicitar-viatico', 'ver-plan-capacitacion',
    ];

    /** `RolPermisoSeeder::PERMISOS_JEFE_TIC`. */
    private const PERMISOS_JEFE = [
        'gestionar-tickets', 'gestionar-base-conocimiento', 'ver-inventario-ti',
        'gestionar-inventario-ti', 'registrar-mantenimiento', 'ver-tickets-todos',
        'asignar-ticket', 'gestionar-tecnicos', 'configurar-sla', 'dar-baja-bien',
    ];

    private const OTORGADOS = [
        'admin-ti'     => self::PERMISOS_JEFE,
        'tecnico-dtic' => ['registrar-mantenimiento'],
        'auditor'      => ['ver-inventario-ti'],
    ];

    private const RETIRADOS = [
        'tecnico-dtic' => ['asignar-ticket', 'ver-tickets-todos'],
    ];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO_NUEVO, 'guard_name' => 'sanctum']);

        if (! Role::where('guard_name', 'sanctum')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        foreach (self::PERMISOS_JEFE as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        $rol = Role::firstOrCreate(['name' => self::ROL, 'guard_name' => 'sanctum']);
        $rol->givePermissionTo(array_merge(
            Permission::where('guard_name', 'sanctum')
                ->whereIn('name', self::PERMISOS_BASE)
                ->pluck('name')
                ->all(),
            self::PERMISOS_JEFE,
        ));

        foreach (self::OTORGADOS as $nombreRol => $permisos) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permisos);
        }

        foreach (self::RETIRADOS as $nombreRol => $permisos) {
            $role = Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first();

            foreach ($permisos as $permiso) {
                if ($role?->hasPermissionTo($permiso, 'sanctum')) {
                    $role->revokePermissionTo($permiso);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::RETIRADOS as $nombreRol => $permisos) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permisos);
        }

        // Lo que tenían antes no se quita: admin-ti ya tenía cinco de los diez.
        $previos = [
            'admin-ti' => ['gestionar-inventario-ti', 'gestionar-tecnicos', 'configurar-sla',
                'ver-tickets-todos', 'gestionar-tickets'],
        ];
        foreach (self::OTORGADOS as $nombreRol => $permisos) {
            $role = Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first();

            foreach (array_diff($permisos, $previos[$nombreRol] ?? [], [self::PERMISO_NUEVO]) as $permiso) {
                if ($role?->hasPermissionTo($permiso, 'sanctum')) {
                    $role->revokePermissionTo($permiso);
                }
            }
        }

        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum`, que no está en `auth.guards`,
        // y revienta. Las asignaciones y los permisos caen por cascada.
        DB::table(config('permission.table_names.roles'))
            ->where('name', self::ROL)
            ->where('guard_name', 'sanctum')
            ->delete();

        Permission::where('name', self::PERMISO_NUEVO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
