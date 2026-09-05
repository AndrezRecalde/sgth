<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda lo que decía una consulta antes de cada corrección.
 *
 * Hasta ahora `PATCH /consultas/{id}` sobrescribía la nota y no quedaba nada:
 * ni quién la cambió, ni cuándo, ni qué decía antes. En una historia clínica
 * eso no es una corrección, es una desaparición —y el diagnóstico es la parte
 * que decide el tratamiento.
 *
 * Se versiona el estado ANTERIOR al guardar el cambio: la fila vigente sigue
 * siendo `consultas_medicas`, y aquí quedan las que dejó atrás. Los campos de
 * texto se cifran igual que en la consulta, porque son el mismo dato clínico.
 *
 * Sin SoftDeletes: es un registro de auditoría; borrarlo derrotaría el motivo
 * de tenerlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_consulta_medica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_medica_id')
                  ->constrained('consultas_medicas')
                  ->cascadeOnDelete();

            $table->string('tipo_atencion', 30)->nullable();
            $table->string('tipo_diagnostico', 30)->nullable();
            $table->text('motivo_consulta')->nullable();
            $table->text('enfermedad_actual')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('diagnostico_detallado')->nullable();
            $table->text('plan_tratamiento')->nullable();
            $table->text('notas_medico')->nullable();

            $table->foreignId('diagnostico_cie10_id')
                  ->nullable()
                  ->constrained('diagnosticos_cie10')
                  ->nullOnDelete();

            // Los secundarios como lista de códigos y no como claves ajenas:
            // es una foto de lo que decía la nota, no una relación viva.
            $table->json('diagnosticos_secundarios')->nullable();

            $table->foreignId('reemplazada_por')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();

            $table->index('consulta_medica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_consulta_medica');
    }
};
