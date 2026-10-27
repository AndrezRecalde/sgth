<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol `tecnico-sso` (docs/plan-roles-y-accesos.md, PR 12; decisión 4 del
 * 2026-10-25).
 *
 * Riesgos laborales solo lo podían llevar admin-uath y asistente-uath, que con
 * el rol traen todo Talento Humano: expedientes, permisos, acciones de
 * personal. El técnico de seguridad y salud ocupacional lleva el módulo entero
 * —factores de riesgo, accidentes, EPP, inspecciones, indicadores— y busca a
 * la persona de un accidente o de una entrega, sin nada más de TH.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const ROL = 'tecnico-sso';

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-asistencia-propia', 'crear-permiso', 'ver-permisos',
        'acceso-autoservicio', 'cambiar-contrasena', 'ver-historia-clinica-propia', 'crear-ticket',
        'ver-ticket-propio', 'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia', 'solicitar-viatico',
        'ver-plan-capacitacion',
    ];

    /** `RolPermisoSeeder::PERMISOS_TECNICO_SSO`. */
    private const PERMISOS = ['gestionar-sso', 'ver-reportes-sso', 'registrar-accidente', 'buscar-servidores'];

    public function up(): void
    {
        if (! Role::where('guard_name', 'sanctum')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        foreach (self::PERMISOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        $base = Permission::where('guard_name', 'sanctum')
            ->whereIn('name', self::PERMISOS_BASE)
            ->pluck('name')
            ->all();

        Role::firstOrCreate(['name' => self::ROL, 'guard_name' => 'sanctum'])
            ->givePermissionTo(array_merge($base, self::PERMISOS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Desde el usuario y no con `Role::users()`: el guard `sanctum` no
        // tiene proveedor configurado y Spatie no sabe qué modelo buscar.
        $conElRol = User::whereHas('roles', fn ($q) => $q->where('name', self::ROL))
            ->orderBy('usuario_ti')
            ->get()
            ->map(fn (User $usuario) => $usuario->usuario_ti ?? $usuario->email);

        // Quitarle el rol en silencio lo dejaría sin acceso sin que nadie lo
        // decida, como al retirar maxima-autoridad (2026_11_27).
        if ($conElRol->isNotEmpty()) {
            throw new RuntimeException(
                'No se retira el rol tecnico-sso: todavía lo tienen '
                . $conElRol->implode(', ')
                . '. Asígneles otro rol desde Usuarios y vuelva a migrar.'
            );
        }

        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum` y revienta. Sus permisos caen
        // por cascada; los permisos en sí se quedan, porque los usan otros roles.
        DB::table(config('permission.table_names.roles'))
            ->where('name', self::ROL)
            ->where('guard_name', 'sanctum')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
