<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Se retiran los roles `maxima-autoridad` y `analista-uath`
 * (docs/plan-roles-y-accesos.md, PR 7; decisiones 3 y 6 del 2026-10-25).
 *
 * - `maxima-autoridad` era un rol de consulta: lectura institucional de
 *   Talento Humano, que conserva el auditor, y el Dashboard y los Reportes del
 *   Dispensario en cifras agregadas, a los que el menú ni siquiera lo dejaba
 *   llegar. No firma nada: el Prefecto firma las acciones de personal como
 *   autoridad nominadora, por su puesto.
 * - `analista-uath` veía y calificaba postulantes y pedía certificaciones
 *   médicas, pero no tenía ningún subsistema donde entrar. Lo cubren
 *   admin-uath y asistente-uath.
 *
 * Si alguien todavía tiene uno de los dos, la migración se detiene y dice
 * quién: borrarle el rol en silencio lo dejaría sin acceso sin que nadie lo
 * decida. Se le asigna otro desde Usuarios y se vuelve a migrar.
 */
return new class extends Migration
{
    private const ROLES = ['maxima-autoridad', 'analista-uath'];

    /** Lo que tenían además de los permisos base, para poder volver atrás. */
    private const PERMISOS = [
        'maxima-autoridad' => [
            'ver-estructura', 'gestionar-distributivo', 'ver-expediente-unidad', 'ver-expediente-todos',
            'ver-asistencia-unidad', 'ver-asistencia-todos', 'ver-permisos-todos', 'ver-vacaciones-unidad',
            'ver-actividades-unidad', 'aprobar-actividades', 'ver-auditoria', 'ver-dashboard-ejecutivo',
            'generar-reportes',
        ],
        'analista-uath' => [
            'ver-expediente-unidad', 'ver-expediente-todos', 'ver-postulantes', 'evaluar-postulantes',
            'solicitar-certificacion-medica',
        ],
    ];

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-asistencia-propia', 'crear-permiso', 'ver-permisos',
        'acceso-autoservicio', 'cambiar-contrasena', 'ver-historia-clinica-propia', 'crear-ticket',
        'ver-ticket-propio', 'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia', 'solicitar-viatico',
        'ver-plan-capacitacion',
    ];

    public function up(): void
    {
        $roles = Role::whereIn('name', self::ROLES)->where('guard_name', 'sanctum')->get();

        // Desde el usuario y no con `Role::users()`: el guard `sanctum` no
        // tiene proveedor configurado y Spatie no sabe qué modelo buscar.
        $conUsuarios = User::whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES))
            ->with('roles:id,name')
            ->orderBy('usuario_ti')
            ->get()
            ->map(fn (User $usuario) => ($usuario->usuario_ti ?? $usuario->email)
                . ' (' . $usuario->roles->pluck('name')->intersect(self::ROLES)->implode(', ') . ')');

        if ($conUsuarios->isNotEmpty()) {
            throw new RuntimeException(
                'No se retiran los roles maxima-autoridad y analista-uath: todavía los tienen '
                . $conUsuarios->implode(', ')
                . '. Asígneles otro rol desde Usuarios y vuelva a migrar.'
            );
        }

        // Con una consulta y no con `$rol->delete()`: el evento de Spatie
        // desvincula a los usuarios por `Role::users()`, que con `sanctum`
        // revienta. Sus permisos y asignaciones caen igual, por la cascada de
        // las claves foráneas.
        Role::whereKey($roles->modelKeys())->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $nombre => $permisos) {
            $existentes = Permission::where('guard_name', 'sanctum')
                ->whereIn('name', [...self::PERMISOS_BASE, ...$permisos])
                ->pluck('name')
                ->all();

            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum'])
                ->syncPermissions($existentes);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
