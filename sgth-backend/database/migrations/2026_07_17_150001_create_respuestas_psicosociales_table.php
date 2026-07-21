<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla intencionalmente sin FK a servidor: la evaluación psicosocial es anónima
        // (Guía MDT para la aplicación del cuestionario de riesgo psicosocial). Solo se
        // guardan datos sociodemográficos agregados, nunca identidad del respondiente.
        Schema::create('respuestas_psicosociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_psicosocial_id')
                ->constrained('evaluaciones_psicosociales')
                ->cascadeOnDelete();

            // Datos sociodemográficos agregados (sin identificar a la persona)
            $table->string('area_trabajo', 20)->nullable(); // administrativa | operativa
            $table->string('nivel_instruccion', 30)->nullable();
            $table->string('antiguedad', 20)->nullable(); // rango de años en la institución
            $table->string('rango_edad', 20)->nullable();
            $table->string('autoidentificacion_etnica', 30)->nullable();
            $table->string('genero', 20)->nullable();

            // Respuestas Likert 1-4 a las 58 preguntas: {"1": 3, "2": 4, ...}
            $table->json('respuestas');

            // Puntaje y nivel de riesgo por dimensión, calculados en el servidor:
            // {"carga_ritmo_trabajo": {"puntaje": 12, "nivel": "medio"}, ...}
            $table->json('puntajes_dimensiones');

            $table->unsignedSmallInteger('puntaje_global');
            $table->string('nivel_riesgo_global', 10);

            $table->timestamp('created_at')->useCurrent();

            $table->index('evaluacion_psicosocial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_psicosociales');
    }
};
