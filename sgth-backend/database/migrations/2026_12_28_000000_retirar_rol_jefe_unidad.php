<?php

use App\Models\User;
use App\Services\Estructura\JefaturaService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Se retira el rol `jefe-unidad` (decisión del 2026-11-13).
 *
 * El jefe ve a su equipo por su puesto de jefatura —como titular, por
 * subrogación o por encargo— en «Mi equipo» del Portal (#644 y #645). El rol
 * lo daba TI a mano y nada lo retiraba cuando la persona dejaba la jefatura.
 * Además lo hacía entrar a Talento Humano, a Estructura y a unas Marcaciones
 * que solo le mostraban lo propio.
 *
 * Si alguien tiene el rol y no dirige ninguna unidad por su puesto, la
 * migración se detiene y dice quién: quitárselo lo dejaría sin ver a su equipo
 * sin que nadie lo decida. Se corrige su puesto, o se le quita el rol desde
 * Usuarios, y se vuelve a migrar.
 *
 * A quien este era su único rol se le da `servidor`: el rol traía los permisos
 * base de todo servidor, y sin ellos se quedaba fuera del Portal.
 */
return new class extends Migration
{
    /** Lo que tenía además de los permisos base, para poder volver atrás. */
    private const PERMISOS = ['ver-estructura', 'ver-asistencia-unidad', 'ver-vacaciones-unidad'];

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
        $rol = Role::where('name', 'jefe-unidad')->where('guard_name', 'sanctum')->first();

        if (! $rol) {
            return;
        }

        // Desde el usuario y no con `Role::users()`: el guard `sanctum` no
        // tiene proveedor configurado y Spatie no sabe qué modelo buscar.
        $usuarios = User::whereHas('roles', fn ($q) => $q->whereKey($rol->id))
            ->with('roles:id,name')
            ->orderBy('usuario_ti')
            ->get();

        $jefaturas = app(JefaturaService::class);
        $sinJefatura = $usuarios->filter(
            fn (User $usuario) => $jefaturas->unidadesQueDirige($usuario->servidor_id) === []
        );

        if ($sinJefatura->isNotEmpty()) {
            throw new RuntimeException(
                'No se retira el rol jefe-unidad: no dirigen ninguna unidad por su puesto '
                . $sinJefatura->map(fn (User $usuario) => $usuario->usuario_ti ?? $usuario->email)->implode(', ')
                . '. Corrija su puesto (php artisan sgth:jefaturas:revisar) o quíteles el rol desde Usuarios, y vuelva a migrar.'
            );
        }

        $soloConEsteRol = $usuarios->filter(fn (User $usuario) => $usuario->roles->count() === 1);

        if ($soloConEsteRol->isNotEmpty()) {
            $servidor = Role::where('name', 'servidor')->where('guard_name', 'sanctum')->first()
                ?? throw new RuntimeException(
                    'No se retira el rol jefe-unidad: el rol servidor no existe. Corra el RolPermisoSeeder y vuelva a migrar.'
                );

            $soloConEsteRol->each(fn (User $usuario) => $usuario->assignRole($servidor));
        }

        // Con una consulta y no con `$rol->delete()`: el evento de Spatie
        // desvincula a los usuarios por `Role::users()`, que con `sanctum`
        // revienta. Sus permisos y asignaciones caen igual, por la cascada de
        // las claves foráneas.
        Role::whereKey($rol->id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Recrea el rol con sus permisos, pero no se lo devuelve a nadie. */
    public function down(): void
    {
        $existentes = Permission::where('guard_name', 'sanctum')
            ->whereIn('name', [...self::PERMISOS_BASE, ...self::PERMISOS])
            ->pluck('name')
            ->all();

        Role::firstOrCreate(['name' => 'jefe-unidad', 'guard_name' => 'sanctum'])
            ->syncPermissions($existentes);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
