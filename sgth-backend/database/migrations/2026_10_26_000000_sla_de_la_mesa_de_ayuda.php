<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El SLA de la mesa de ayuda (docs/diseno-subsistema-tic.md, 4.3; fase 3.2).
 *
 * - `slas` pasa a ir por categoría y prioridad (D7): una fila sin categoría es
 *   la regla general de esa prioridad, y una con categoría la afina. Las horas
 *   admiten fracciones. Si no hay ninguna fila, rige `config/helpdesk.php`.
 * - Cada ticket guarda las horas que le tocaron al abrirse, para que cambiar la
 *   tabla después no mueva los plazos de los tickets en curso; cuándo vence la
 *   primera respuesta y cuándo se dio; la pausa mientras espera al usuario y
 *   lo pausado acumulado; y cuándo salieron la alerta y el vencimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slas', function (Blueprint $table) {
            $table->dropUnique(['prioridad']);
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_ticket');
        });
        DB::statement('ALTER TABLE slas ALTER COLUMN tiempo_resolucion_horas TYPE numeric(6,2)');
        DB::statement('ALTER TABLE slas ALTER COLUMN tiempo_respuesta_horas TYPE numeric(6,2)');
        DB::statement('CREATE UNIQUE INDEX slas_prioridad_general_unique ON slas (prioridad) WHERE categoria_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX slas_categoria_prioridad_unique ON slas (categoria_id, prioridad) WHERE categoria_id IS NOT NULL AND deleted_at IS NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->decimal('sla_respuesta_horas', 6, 2)->nullable();
            $table->decimal('sla_resolucion_horas', 6, 2)->nullable();
            $table->timestamp('vence_respuesta_en')->nullable();
            $table->timestamp('primera_respuesta_en')->nullable();
            $table->timestamp('pausado_desde')->nullable();
            $table->unsignedInteger('minutos_pausados')->default(0);
            $table->timestamp('alerta_sla_en')->nullable();
            $table->timestamp('sla_vencido_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'sla_respuesta_horas', 'sla_resolucion_horas', 'vence_respuesta_en', 'primera_respuesta_en',
                'pausado_desde', 'minutos_pausados', 'alerta_sla_en', 'sla_vencido_en',
            ]);
        });

        DB::statement('DROP INDEX IF EXISTS slas_categoria_prioridad_unique');
        DB::statement('DROP INDEX IF EXISTS slas_prioridad_general_unique');
        Schema::table('slas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
        });
        DB::statement('ALTER TABLE slas ALTER COLUMN tiempo_resolucion_horas TYPE integer USING ceil(tiempo_resolucion_horas)');
        DB::statement('ALTER TABLE slas ALTER COLUMN tiempo_respuesta_horas TYPE integer USING ceil(tiempo_respuesta_horas)');
        Schema::table('slas', function (Blueprint $table) {
            $table->unique('prioridad');
        });
    }
};
