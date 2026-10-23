<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El resultado contra la orden (fase 3 de docs/diseno-examenes-medicos.md).
 *
 * Un resultado puede venir de una orden, y un solo archivo del laboratorio
 * suele cubrir varios de sus exámenes: la biometría, la glucosa y el perfil
 * lipídico en el mismo PDF. El pivote dice cuáles cubre, para saber qué volvió
 * y qué falta.
 *
 * Solo columnas nuevas: los resultados de hoy no se tocan y quedan «sin
 * orden», con sus archivos donde están. La revisión del médico (estado,
 * interpretación, comentario) llega con la fase 4, que es la que la usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultados_medicos', function (Blueprint $table) {
            // Restrict: una orden no se borra, se anula, y sus resultados se
            // quedan (regla 3 del diseño).
            $table->foreignId('orden_examen_id')
                  ->nullable()
                  ->after('consulta_medica_id')
                  ->constrained('ordenes_examen')
                  ->restrictOnDelete();
        });

        Schema::create('resultado_orden_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resultado_medico_id')
                  ->constrained('resultados_medicos')
                  ->cascadeOnDelete();
            $table->foreignId('orden_examen_item_id')
                  ->constrained('orden_examen_items')
                  ->cascadeOnDelete();

            $table->unique(['resultado_medico_id', 'orden_examen_item_id']);
            $table->index('orden_examen_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado_orden_items');

        Schema::table('resultados_medicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_examen_id');
        });
    }
};
