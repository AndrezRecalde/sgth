<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los tickets de la mesa de ayuda, rehechos (docs/diseno-subsistema-tic.md,
 * 4.2 y 4.3; fase 3.1). Las tablas del módulo están vacías: se ajustan sin
 * migrar datos.
 *
 * - `tickets` gana la prioridad, el canal (Portal o registrado por TIC), la
 *   subunidad de TIC que atiende (D6), el nivel (1 mesa, 2 especialista,
 *   3 proveedor), la solución, cuándo y quién la resolvió, las reaperturas, la
 *   anulación y quién lo registró. El SLA deja de ser obligatorio: lo calcula
 *   la fase 3.2 a partir de la categoría y la prioridad.
 * - `historial_estados_ticket`: cada cambio de estado, quién y cuándo.
 * - `comentarios_ticket` cambia el enlace suelto (`evidencia_url`) por un
 *   archivo adjunto en el disco privado.
 * - `categorias_ticket` gana la subunidad que la atiende por defecto, una
 *   descripción y si está activa, y se siembran las de D7.
 */
return new class extends Migration
{
    /** D7: la base mientras TIC entrega su lista, con la subunidad que la atiende. */
    private const CATEGORIAS = [
        ['Hardware', true, 'Soporte Técnico', 'Computadores, monitores, teclados, ratones y periféricos que no funcionan'],
        ['Software', false, 'Soporte Técnico', 'Programas de oficina, instalaciones y actualizaciones'],
        ['Impresoras', true, 'Soporte Técnico', 'Impresoras y escáneres: atascos, tóner, conexión'],
        ['Red e internet', false, 'Infraestructura y Redes', 'Sin conexión, red lenta, wifi, puntos de red'],
        ['Correo institucional', false, 'Infraestructura y Redes', 'Cuentas de correo, buzón lleno, envío y recepción'],
        ['Accesos y contraseñas', false, 'Seguridad de la Información', 'Usuarios bloqueados, contraseñas, permisos de carpetas'],
        ['Sistemas institucionales', false, 'Desarrollo de Software', 'SGTH, Quipux y demás sistemas del GAD'],
    ];

    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('prioridad', 20)->default('media');
            $table->string('canal', 20)->default('portal');
            $table->foreignId('unidad_tic_id')->nullable()->constrained('unidades_administrativas');
            $table->unsignedTinyInteger('nivel')->default(1);
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->text('solucion')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users');
            $table->unsignedSmallInteger('reabierto_veces')->default(0);
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion', 500)->nullable();
            $table->index(['estado', 'created_at']);
            $table->index(['tecnico_id', 'estado']);
        });
        DB::statement('ALTER TABLE tickets ALTER COLUMN sla_id DROP NOT NULL');

        Schema::create('historial_estados_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('detalle', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'id']);
        });

        Schema::table('comentarios_ticket', function (Blueprint $table) {
            $table->dropColumn('evidencia_url');
            $table->string('archivo_ruta')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->string('archivo_mime', 100)->nullable();
        });

        Schema::table('categorias_ticket', function (Blueprint $table) {
            $table->string('descripcion', 300)->nullable();
            $table->foreignId('unidad_tic_id')->nullable()->constrained('unidades_administrativas');
            $table->boolean('activa')->default(true);
        });

        $this->sembrarCategorias();
    }

    public function down(): void
    {
        Schema::table('categorias_ticket', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_tic_id');
            $table->dropColumn(['descripcion', 'activa']);
        });

        Schema::table('comentarios_ticket', function (Blueprint $table) {
            $table->dropColumn(['archivo_ruta', 'archivo_nombre', 'archivo_mime']);
            $table->string('evidencia_url')->nullable();
        });

        Schema::dropIfExists('historial_estados_ticket');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['estado', 'created_at']);
            $table->dropIndex(['tecnico_id', 'estado']);
            $table->dropConstrainedForeignId('unidad_tic_id');
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropConstrainedForeignId('resuelto_por');
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['prioridad', 'canal', 'nivel', 'solucion', 'resuelto_en', 'reabierto_veces', 'anulado_en', 'motivo_anulacion']);
        });
    }

    /** Solo si no hay ninguna: si TIC ya cargó las suyas, no se tocan. */
    private function sembrarCategorias(): void
    {
        if (DB::table('categorias_ticket')->whereNull('deleted_at')->exists()) {
            return;
        }

        $tic = DB::table('unidades_administrativas')->where('codigo', config('helpdesk.unidad_tic_codigo'))->value('id');
        $subunidades = $tic
            ? DB::table('unidades_administrativas')->where('unidad_padre_id', $tic)->pluck('id', 'nombre')
            : collect();

        foreach (self::CATEGORIAS as [$nombre, $hardware, $subunidad, $descripcion]) {
            DB::table('categorias_ticket')->insert([
                'nombre'        => $nombre,
                'es_hardware'   => $hardware,
                'descripcion'   => $descripcion,
                'unidad_tic_id' => $subunidades[$subunidad] ?? null,
                'activa'        => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
};
