<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla intencionalmente sin FK a servidor: el tamizaje ASSIST se aplica de forma
        // anónima y confidencial (Instructivo MDT-MSP-2019-038, Fase 4: "no se solicitará
        // nombres, número de cédula o firma de los trabajadores").
        Schema::create('respuestas_assist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_assist_id')
                ->constrained('evaluaciones_assist')
                ->cascadeOnDelete();

            // Respuestas crudas P1-P8 por sustancia: {"tabaco": {"p1": true, "p2": "semanalmente", ...}, ...}
            $table->json('respuestas');

            // Puntaje ASSIST (suma P2 a P7, ver manual Cap. 13) por sustancia consumida:
            // {"tabaco": 25, "alcohol": 14, ...} — solo sustancias con P1 = sí
            $table->json('puntajes');

            // Nivel de riesgo por sustancia, calculado en el servidor contra los puntos de
            // corte del Cap. 14: {"tabaco": "alto", "alcohol": "moderado", ...}
            $table->json('niveles_riesgo');

            // Nivel de riesgo más alto entre todas las sustancias, para filtrado rápido en dashboards
            $table->string('nivel_riesgo_maximo', 10);

            // P8: consumo por vía inyectada (no se suma al puntaje, pero es indicador de alto riesgo)
            $table->string('uso_inyectable', 20)->default('no_nunca');

            $table->timestamp('created_at')->useCurrent();

            $table->index('evaluacion_assist_id');
            $table->index('nivel_riesgo_maximo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_assist');
    }
};
