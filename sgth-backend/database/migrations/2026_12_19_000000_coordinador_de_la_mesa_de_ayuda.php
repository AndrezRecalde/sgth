<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La mesa de ayuda centralizada (respuestas de TIC del 2026-11-06,
 * docs/Preguntas_TIC_Centralizacion_Mesa_de_Ayuda.docx).
 *
 * - Permiso nuevo `registrar-ticket-a-nombre`: registrar el ticket de quien
 *   llamó o vino al mostrador. Iba con `gestionar-tickets`, que tiene el
 *   técnico; ahora es del jefe de TIC, de admin-ti y del coordinador.
 * - Rol nuevo `coordinador-mesa-ayuda`: recibe todos los pedidos, los reparte,
 *   resuelve lo simple y se encarga del inventario (propone bajas, no las
 *   aprueba). Préstamos, el directorio y lo demás, con permisos adicionales
 *   desde Usuarios. Lo designa el jefe de TIC y lo asigna admin-ti.
 *
 * Que el técnico vea solo lo que le asignan y escale de vuelta al coordinador
 * lo decide el código por permiso (`asignar-ticket`): no hay nada que migrar.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const ROL = 'coordinador-mesa-ayuda';

    private const PERMISO = 'registrar-ticket-a-nombre';

    private const CON_EL_PERMISO = ['jefe-tic', 'admin-ti'];

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-asistencia-propia', 'crear-permiso', 'ver-permisos',
        'acceso-autoservicio', 'cambiar-contrasena', 'ver-historia-clinica-propia', 'crear-ticket',
        'ver-ticket-propio', 'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia', 'solicitar-viatico',
        'ver-plan-capacitacion',
    ];

    /** `RolPermisoSeeder::PERMISOS_COORDINADOR_MESA_AYUDA`. */
    private const PERMISOS = [
        'gestionar-tickets', 'ver-tickets-todos', 'asignar-ticket', self::PERMISO,
        'gestionar-problemas-frecuentes', 'ver-inventario-ti', 'gestionar-inventario-ti',
        'registrar-mantenimiento', 'buscar-servidores',
    ];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        if (! Role::where('guard_name', 'sanctum')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        foreach (self::CON_EL_PERMISO as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
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
        // decida, como en tecnico-asistencia (2026_12_14).
        if ($conElRol->isNotEmpty()) {
            throw new RuntimeException(
                'No se retira el rol coordinador-mesa-ayuda: todavía lo tienen '
                . $conElRol->implode(', ')
                . '. Asígneles otro rol desde Usuarios y vuelva a migrar.'
            );
        }

        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum` y revienta.
        DB::table(config('permission.table_names.roles'))
            ->where('name', self::ROL)
            ->where('guard_name', 'sanctum')
            ->delete();
        // Las asignaciones del permiso a roles y a usuarios caen por cascada.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
