<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol `auxiliar-farmacia` (docs/plan-roles-y-accesos.md, PR 13; decisión 4
 * del 2026-10-25).
 *
 * El mostrador de la farmacia lo atendían Enfermería y la administración del
 * Dispensario, que con el rol traen las historias clínicas, la agenda y las
 * colas. El auxiliar consulta el inventario y su kárdex, da de baja lo vencido
 * o roto, y despacha las recetas; el JSON de las recetas ya no lleva las notas
 * de la consulta (PR 5). No entra a historias, consultas, agenda ni búsqueda
 * de pacientes.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const ROL = 'auxiliar-farmacia';

    /** Los permisos base de todo servidor, como en `RolPermisoSeeder`. */
    private const PERMISOS_BASE = [
        'ver-expediente-propio', 'ver-asistencia-propia', 'crear-permiso', 'ver-permisos',
        'acceso-autoservicio', 'cambiar-contrasena', 'ver-historia-clinica-propia', 'crear-ticket',
        'ver-ticket-propio', 'registrar-actividades', 'exportar-informe-actividades',
        'responder-encuesta-clima', 'inscribirse-curso', 'ver-evaluacion-propia', 'solicitar-viatico',
        'ver-plan-capacitacion',
    ];

    private const PERMISOS = ['despachar-medicamento'];

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
                'No se retira el rol auxiliar-farmacia: todavía lo tienen '
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
