<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La custodia de los equipos (docs/diseno-subsistema-tic.md, 5.3; fase 1.3).
 *
 * - `usuario_servidor_id`: quien usa el equipo cuando no es el custodio que
 *   firma el acta (D3). En el Excel de TIC era «CUSTODIA ADMINISTRATIVO»
 *   frente a «ASIGNADO».
 * - `motivo` y `asignacion_anterior_id`: un traspaso cierra la custodia
 *   anterior y abre la nueva, enlazada a la que reemplaza. El Excel lo traía
 *   como «USUARIO ANTERIOR».
 * - El memorando de TIC (`Memorando Nº NNN GADPE-HAP-GTIC-AAAA`) se hace en
 *   Quipux: aquí van su número y su PDF (D4).
 * - `soporte_anterior`: el número de soporte del sistema antiguo, hasta que
 *   arranque la mesa de ayuda (D1).
 * - Un equipo tiene una sola custodia vigente: lo garantiza la base, no solo
 *   el servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_bien', function (Blueprint $table) {
            $table->foreignId('usuario_servidor_id')->nullable()->after('servidor_id')
                ->constrained('servidores')->nullOnDelete();
            $table->string('motivo', 20)->default('entrega')->after('estado');
            $table->foreignId('asignacion_anterior_id')->nullable()->after('motivo')
                ->constrained('asignaciones_bien')->nullOnDelete();
            $table->string('memorando_numero', 100)->nullable();
            $table->string('memorando_archivo')->nullable();
            $table->string('memorando_nombre')->nullable();
            $table->string('soporte_anterior', 30)->nullable();
        });

        DB::statement("CREATE UNIQUE INDEX asignaciones_bien_vigente_unique ON asignaciones_bien (bien_informatico_id) WHERE estado = 'activa' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asignaciones_bien_vigente_unique');

        Schema::table('asignaciones_bien', function (Blueprint $table) {
            $table->dropForeign(['usuario_servidor_id']);
            $table->dropForeign(['asignacion_anterior_id']);
            $table->dropColumn([
                'usuario_servidor_id', 'motivo', 'asignacion_anterior_id',
                'memorando_numero', 'memorando_archivo', 'memorando_nombre', 'soporte_anterior',
            ]);
        });
    }
};
