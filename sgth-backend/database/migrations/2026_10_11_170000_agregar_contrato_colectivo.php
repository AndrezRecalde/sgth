<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El contrato colectivo de los obreros y el incremento en lote (diseño de
 * Acciones de Personal, 9.15; fase 4.6).
 *
 * - `contratos_colectivos`: el documento y las cláusulas que cambian cálculos.
 *   Sin el dictamen del Ministerio de Finanzas, el contrato se tiene por
 *   inexistente (Acuerdo MDT-2024-080) y sus cláusulas no se aplican.
 * - `salarios_basicos`: el salario básico unificado de cada año. Lo usan los
 *   topes del Mandato 2 (7 por año de servicio, 210 en total).
 * - `lotes_accion`: los actos que nacen juntos. Hoy, el incremento de los
 *   obreros; en la fase 7, la revisión de clasificación y el traspaso de
 *   puestos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_colectivos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 100)->unique();
            $table->date('fecha_suscripcion')->nullable();
            $table->date('fecha_registro_mdt')->nullable();
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->string('dictamen_finanzas_numero', 100)->nullable();
            $table->date('dictamen_finanzas_fecha')->nullable();
            // Las cláusulas que cambian cálculos. Vacías: rige la ley sola.
            $table->unsignedSmallInteger('dias_adicionales_vacaciones')->default(0);
            $table->decimal('bonificacion_jubilacion_sbu', 4, 2)->nullable();
            $table->decimal('bonificacion_retiro_sbu', 4, 2)->nullable();
            $table->decimal('incremento_pactado_porcentaje', 5, 2)->nullable();
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE contratos_colectivos ADD CONSTRAINT contratos_colectivos_vigencia_check
            CHECK (vigencia_hasta IS NULL OR vigencia_hasta >= vigencia_desde)');
        DB::statement('ALTER TABLE contratos_colectivos ADD CONSTRAINT contratos_colectivos_clausulas_check
            CHECK (dias_adicionales_vacaciones <= 30
                AND (bonificacion_jubilacion_sbu IS NULL OR bonificacion_jubilacion_sbu BETWEEN 0 AND 7)
                AND (bonificacion_retiro_sbu IS NULL OR bonificacion_retiro_sbu BETWEEN 0 AND 7)
                AND (incremento_pactado_porcentaje IS NULL OR incremento_pactado_porcentaje > 0 AND incremento_pactado_porcentaje <= 100))');

        Schema::create('salarios_basicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->decimal('monto', 10, 2);
            $table->string('acuerdo', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE salarios_basicos ADD CONSTRAINT salarios_basicos_monto_check CHECK (monto > 0)');

        Schema::create('lotes_accion', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->text('descripcion');
            $table->date('fecha_efectiva');
            // El acuerdo ministerial de la tabla sectorial o la cláusula del
            // contrato colectivo: hace las veces de la adenda de cada obrero.
            $table->string('documento_origen', 100);
            $table->string('dictamen_presupuestario_ref', 255);
            // Lo que se eligió para prepararlo: el modo, el valor, la unidad.
            $table->json('parametros');
            $table->foreignId('contrato_colectivo_id')->nullable()->constrained('contratos_colectivos')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE lotes_accion ADD CONSTRAINT lotes_accion_tipo_check
            CHECK (tipo IN ('incremento_obreros'))");

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('lote_id')->nullable()->constrained('lotes_accion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lote_id');
        });

        Schema::dropIfExists('lotes_accion');
        Schema::dropIfExists('salarios_basicos');
        Schema::dropIfExists('contratos_colectivos');
    }
};
