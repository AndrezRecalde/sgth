<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los problemas frecuentes de la mesa de ayuda (respuestas de TIC del
 * 2026-11-05, docs/Preguntas_TIC_Problemas_Frecuentes.docx).
 *
 * Un catálogo que define TIC: el servidor toca «La impresora no imprime» y el
 * ticket sale con el asunto, la descripción, la categoría, la prioridad y el
 * tipo que TIC fijó, sin escribir nada. La subunidad y el plazo siguen
 * saliendo de la categoría (pregunta 5).
 *
 * - `problemas_frecuentes`: no se borran; uno que ya no sirve se desactiva.
 * - El permiso `gestionar-problemas-frecuentes`, del jefe de TIC y de
 *   admin-ti. Los técnicos que el jefe designe lo reciben desde Usuarios
 *   (preguntas 2 y 12). Va aquí y no solo en `RolPermisoSeeder` por lo mismo
 *   que los demás permisos: volver a correr el seeder en producción se
 *   llevaría los ajustes hechos desde Usuarios.
 * - Se siembran los 7 que eligió TIC, solo si la tabla está vacía y en la
 *   categoría que se llame igual; si TIC renombró una, ese problema no se crea.
 */
return new class extends Migration
{
    private const PERMISO = 'gestionar-problemas-frecuentes';

    private const ROLES = ['jefe-tic', 'admin-ti'];

    /** Nombre, categoría, tipo, prioridad y la descripción que lleva el ticket. */
    private const PROBLEMAS = [
        ['Ayuda con FirmaEC', 'Software', 'solicitud', 'media',
            'Necesito ayuda con FirmaEC: instalarlo, actualizarlo o resolver un error al firmar.'],
        ['El equipo no enciende o está muy lento', 'Hardware', 'incidente', 'alta',
            'Mi equipo no enciende o funciona muy lento.'],
        ['La impresora no imprime', 'Impresoras', 'incidente', 'media',
            'La impresora que uso no imprime.'],
        ['Instalar o conectar una impresora', 'Impresoras', 'solicitud', 'media',
            'Necesito instalar o conectar una impresora en mi equipo.'],
        ['No tengo internet', 'Red e internet', 'incidente', 'alta',
            'No tengo conexión a internet en mi equipo.'],
        ['No puedo entrar a mi correo', 'Correo institucional', 'incidente', 'media',
            'No puedo entrar a mi correo institucional.'],
        ['Desbloquear mi usuario o cambiar mi contraseña', 'Accesos y contraseñas', 'solicitud', 'media',
            'Necesito desbloquear mi usuario o cambiar mi contraseña.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('problemas_frecuentes')) {
            Schema::create('problemas_frecuentes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80);
                $table->foreignId('categoria_id')->constrained('categorias_ticket');
                $table->string('tipo_ticket', 20)->default('incidente');
                $table->string('prioridad', 20)->default('media');
                $table->string('descripcion', 1000);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['activo', 'orden']);
            });
        }

        $this->sembrar();

        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);
        foreach (self::ROLES as $nombreRol) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo(self::PERMISO);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones a roles y los permisos adicionales de cada usuario
        // caen por cascada con el permiso.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('problemas_frecuentes');
    }

    private function sembrar(): void
    {
        if (DB::table('problemas_frecuentes')->exists()) {
            return;
        }

        $categorias = DB::table('categorias_ticket')->whereNull('deleted_at')->get(['id', 'nombre'])
            ->keyBy(fn ($c) => mb_strtolower($c->nombre));

        $orden = 0;
        foreach (self::PROBLEMAS as [$nombre, $categoria, $tipo, $prioridad, $descripcion]) {
            $categoriaId = $categorias[mb_strtolower($categoria)]->id ?? null;
            if (! $categoriaId) {
                continue;
            }

            DB::table('problemas_frecuentes')->insert([
                'nombre'       => $nombre,
                'categoria_id' => $categoriaId,
                'tipo_ticket'  => $tipo,
                'prioridad'    => $prioridad,
                'descripcion'  => $descripcion,
                'orden'        => ++$orden,
                'activo'       => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
};
