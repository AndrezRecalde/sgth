<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La sección J de la ficha ocupacional alimentada desde los resultados (fase 5
 * de docs/diseno-examenes-medicos.md, 5.6).
 *
 * El médico ya no copia a mano lo que el Dispensario tiene subido: lo elige y
 * la fila guarda de qué resultado salió. Lo escrito a mano, para lo que no
 * tiene archivo, sigue igual y queda sin enlace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('femo_examenes', function (Blueprint $table) {
            // Un resultado se retira en blando y la fila no se pierde; si un
            // día se borrara de verdad, la fila se queda con lo copiado.
            $table->foreignId('resultado_medico_id')
                  ->nullable()
                  ->after('ficha_id')
                  ->constrained('resultados_medicos')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('femo_examenes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resultado_medico_id');
        });
    }
};
