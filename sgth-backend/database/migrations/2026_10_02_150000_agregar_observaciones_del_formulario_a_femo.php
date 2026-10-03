<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del impreso SNS-MSP/HCU-form.123/2025 que la ficha no tenía.
 *
 * Cotejados contra el formulario en blanco el 2026-10-02:
 * - «Observación» al pie de la sección C (antecedentes personales),
 * - «Observación» al pie de la sección F (examen físico regional),
 * - «Observaciones» al pie de la sección J (resultados de exámenes),
 * - y en los dos bloques reproductivos, la columna «Registrar resultado
 *   únicamente si interfiere con la actividad laboral y previa autorización
 *   del titular».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->text('observacion_antecedentes')->nullable()->after('medicacion_habitual_cantidad');
            $table->text('observacion_examen_fisico')->nullable()->after('observacion_antecedentes');
            $table->text('observacion_examenes')->nullable()->after('observacion_examen_fisico');
        });

        Schema::table('femo_antecedentes_reproductivos', function (Blueprint $table) {
            $table->text('examenes_resultado')->nullable()->after('examenes_tiempo_anios');
        });
    }

    public function down(): void
    {
        Schema::table('femo_antecedentes_reproductivos', function (Blueprint $table) {
            $table->dropColumn('examenes_resultado');
        });

        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropColumn(['observacion_antecedentes', 'observacion_examen_fisico', 'observacion_examenes']);
        });
    }
};
