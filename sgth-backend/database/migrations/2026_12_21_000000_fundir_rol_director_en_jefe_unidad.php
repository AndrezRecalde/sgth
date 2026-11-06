<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `director` se funde en `jefe-unidad` (decisión del 2026-11-06).
 *
 * Los dos veían el mismo menú y solo su propia unidad. Lo que el director tenía
 * de más no lo usaba ninguna pantalla: `ver-dashboard-ejecutivo` (las encuestas
 * de la mesa de ayuda por API), `generar-reportes` (no lo comprueba nadie) y,
 * por su nombre, las rutas de reportería y los resultados de la encuesta de
 * clima, que no tienen pantalla.
 *
 * A diferencia del retiro de `maxima-autoridad`, aquí sí se reasigna: quien
 * tenía `director` recibe `jefe-unidad`, que le da lo mismo que ya veía.
 * Volver atrás recrea el rol, pero no se lo devuelve a nadie: quedan como
 * jefes de unidad.
 */
return new class extends Migration
{
    /** Lo que tenía además de los permisos base, para poder volver atrás. */
    private const PERMISOS = [
        'ver-estructura', 'ver-asistencia-unidad', 'ver-vacaciones-unidad',
        'ver-dashboard-ejecutivo', 'generar-reportes',
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
        $director = Role::where('name', 'director')->where('guard_name', 'sanctum')->first();

        if (! $director) {
            return;
        }

        // Desde el usuario y no con `Role::users()`: el guard `sanctum` no
        // tiene proveedor configurado y Spatie no sabe qué modelo buscar.
        $directores = User::whereHas('roles', fn ($q) => $q->whereKey($director->id))->get();

        if ($directores->isNotEmpty()) {
            $jefe = Role::where('name', 'jefe-unidad')->where('guard_name', 'sanctum')->first();

            if (! $jefe) {
                throw new RuntimeException(
                    'No se funde director en jefe-unidad: el rol jefe-unidad no existe. '
                    . 'Corra el RolPermisoSeeder y vuelva a migrar.'
                );
            }

            $directores->each(fn (User $usuario) => $usuario->assignRole($jefe));
        }

        // Con una consulta y no con `$rol->delete()`: el evento de Spatie
        // desvincula a los usuarios por `Role::users()`, que con `sanctum`
        // revienta. Sus permisos y asignaciones caen igual, por la cascada de
        // las claves foráneas.
        Role::whereKey($director->id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $existentes = Permission::where('guard_name', 'sanctum')
            ->whereIn('name', [...self::PERMISOS_BASE, ...self::PERMISOS])
            ->pluck('name')
            ->all();

        Role::firstOrCreate(['name' => 'director', 'guard_name' => 'sanctum'])
            ->syncPermissions($existentes);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
