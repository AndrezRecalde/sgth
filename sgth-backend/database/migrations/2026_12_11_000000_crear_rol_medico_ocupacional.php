<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol `medico-ocupacional` (decisión del 2026-10-29).
 *
 * Todo médico iniciaba, llenaba y dictaminaba la FEMO, y veía las solicitudes
 * y la cobertura de Talento Humano. Desde aquí eso es del médico que hace las
 * evaluaciones ocupacionales: un rol complementario que se da junto con
 * `medico` y solo suma `ver-fichas-sso-med`, `gestionar-fichas-sso-med` y
 * `ver-certificaciones-medicas`. El médico general los pierde; la cola, la
 * especialidad y «Mi jornada» siguen en `medico`.
 *
 * Para que nadie se quede con una ficha a medias, el rol nuevo se le da a todo
 * médico que ya evaluó una FEMO. A los demás se lo asigna TI desde Usuarios.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se crea nada: lo hace el seeder, que ya incluye esto.
 */
return new class extends Migration
{
    private const ROL = 'medico-ocupacional';

    private const PERMISOS = [
        'ver-fichas-sso-med',
        'gestionar-fichas-sso-med',
        'ver-certificaciones-medicas',
    ];

    public function up(): void
    {
        if (! Role::where('guard_name', 'sanctum')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        foreach (self::PERMISOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        Role::firstOrCreate(['name' => self::ROL, 'guard_name' => 'sanctum'])
            ->givePermissionTo(self::PERMISOS);

        $medico = Role::where('name', 'medico')->where('guard_name', 'sanctum')->first();

        foreach (self::PERMISOS as $nombre) {
            $medico?->revokePermissionTo($nombre);
        }

        // Los médicos que ya evaluaron una FEMO, también las que siguen en
        // borrador: sin el rol no podrían terminarlas.
        $evaluadores = DB::table('fichas_salud_ocupacional')
            ->whereNull('deleted_at')
            ->whereNotNull('evaluador_id')
            ->distinct()
            ->pluck('evaluador_id');

        User::whereIn('id', $evaluadores)
            ->whereHas('roles', fn ($q) => $q->where('name', 'medico'))
            ->get()
            ->each(fn (User $usuario) => $usuario->assignRole(self::ROL));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Todo médico recupera los permisos, así que quitar el rol no deja a
        // nadie sin acceso, a diferencia de tecnico-sso (2026_11_30).
        Role::where('name', 'medico')->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo(self::PERMISOS);

        // Por tabla y no con `$rol->delete()`: el evento de Spatie busca el
        // modelo de usuario del guard `sanctum` y revienta. Sus asignaciones
        // caen por cascada; los permisos se quedan, porque los usan otros roles.
        DB::table(config('permission.table_names.roles'))
            ->where('name', self::ROL)
            ->where('guard_name', 'sanctum')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
