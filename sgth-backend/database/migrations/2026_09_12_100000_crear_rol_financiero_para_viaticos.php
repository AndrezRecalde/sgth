<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol `financiero`: quien aprueba, opera y contabiliza los viáticos.
 *
 * El módulo lo usa un responsable de Gestión Financiera, pero no había rol para
 * él: `gestionar-viaticos` solo lo tenían admin-uath y asistente-uath, y darle
 * uno de esos roles le abría la nómina y los expedientes. Además, casi ninguna
 * ruta pedía permiso, así que en la práctica cualquier usuario operaba todo.
 *
 * Decidido con el usuario: Financiero opera; Talento Humano solo consulta.
 * admin-uath conserva `ver-viaticos-todos` y pierde `gestionar-viaticos` y
 * `gestionar-tarifas-viatico`; asistente-uath pierde `gestionar-viaticos`.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye el rol.
 */
return new class extends Migration
{
    private const ROL = 'financiero';

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-rol-pago-propio', 'ver-asistencia-propia',
        'crear-permiso', 'ver-permisos', 'acceso-autoservicio', 'cambiar-contrasena',
        'ver-historia-clinica-propia', 'crear-ticket', 'ver-ticket-propio',
        'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia',
        'solicitar-viatico', 'ver-plan-capacitacion',
    ];

    private const PERMISOS_VIATICOS = [
        'ver-viaticos-todos', 'aprobar-viatico', 'gestionar-viaticos',
        'liquidar-viatico', 'gestionar-tarifas-viatico',
    ];

    private const RETIRADOS = [
        'admin-uath'     => ['gestionar-viaticos', 'gestionar-tarifas-viatico'],
        'asistente-uath' => ['gestionar-viaticos'],
    ];

    public function up(): void
    {
        if (! Role::where('guard_name', 'sanctum')->exists()) {
            return;
        }

        foreach (self::PERMISOS_VIATICOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        $rol = Role::firstOrCreate(['name' => self::ROL, 'guard_name' => 'sanctum']);
        $rol->givePermissionTo(array_merge(
            Permission::where('guard_name', 'sanctum')
                ->whereIn('name', self::PERMISOS_BASE)
                ->pluck('name')
                ->all(),
            self::PERMISOS_VIATICOS,
        ));

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

        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum`, que no está en `auth.guards`,
        // y revienta. Las asignaciones y los permisos caen por cascada.
        DB::table(config('permission.table_names.roles'))
            ->where('name', self::ROL)
            ->where('guard_name', 'sanctum')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
