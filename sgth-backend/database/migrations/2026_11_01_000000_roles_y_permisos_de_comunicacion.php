<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los roles y permisos de Comunicación Social
 * (docs/diseno-comunicacion-social.md, 7.3 y decisión D2).
 *
 * - Rol `comunicacion-social`: el equipo que toma las fotos de los servidores
 *   e imprime sus credenciales.
 * - Rol `responsable-identidad`: la persona designada para cambiar los logos y
 *   los datos institucionales. No es el director por su cargo: se asigna a una
 *   persona desde Usuarios y se le quita igual cuando cambia la designación.
 * - `admin-ti` recibe todo, de forma explícita, para que la pantalla de
 *   Usuarios muestre lo que el rol puede hacer.
 * - `admin-uath` y `auditor` consultan las credenciales.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye todo esto.
 */
return new class extends Migration
{
    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-rol-pago-propio', 'ver-asistencia-propia',
        'crear-permiso', 'ver-permisos', 'acceso-autoservicio', 'cambiar-contrasena',
        'ver-historia-clinica-propia', 'crear-ticket', 'ver-ticket-propio',
        'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia',
        'solicitar-viatico', 'ver-plan-capacitacion',
    ];

    /** `RolPermisoSeeder::PERMISOS_COMUNICACION_SOCIAL` y `PERMISOS_RESPONSABLE_IDENTIDAD`. */
    private const ROLES_NUEVOS = [
        'comunicacion-social'   => ['gestionar-fotografias', 'gestionar-credenciales', 'ver-credenciales'],
        'responsable-identidad' => ['gestionar-identidad-institucional'],
    ];

    private const OTORGADOS = [
        'admin-ti'   => [
            'gestionar-fotografias', 'gestionar-credenciales', 'ver-credenciales',
            'gestionar-identidad-institucional',
        ],
        'admin-uath' => ['ver-credenciales'],
        'auditor'    => ['ver-credenciales'],
    ];

    private const PERMISOS_NUEVOS = [
        'gestionar-fotografias', 'gestionar-credenciales', 'ver-credenciales',
        'gestionar-identidad-institucional',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS_NUEVOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        if (! Role::where('guard_name', 'sanctum')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $base = Permission::where('guard_name', 'sanctum')
            ->whereIn('name', self::PERMISOS_BASE)
            ->pluck('name')
            ->all();

        foreach (self::ROLES_NUEVOS as $nombreRol => $permisos) {
            Role::firstOrCreate(['name' => $nombreRol, 'guard_name' => 'sanctum'])
                ->givePermissionTo(array_merge($base, $permisos));
        }

        foreach (self::OTORGADOS as $nombreRol => $permisos) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum`, que no está en `auth.guards`,
        // y revienta. Las asignaciones y los permisos caen por cascada.
        DB::table(config('permission.table_names.roles'))
            ->whereIn('name', array_keys(self::ROLES_NUEVOS))
            ->where('guard_name', 'sanctum')
            ->delete();

        // Los permisos nuevos se los llevan también de admin-ti, admin-uath y
        // auditor, que no los tenían antes.
        Permission::whereIn('name', self::PERMISOS_NUEVOS)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
