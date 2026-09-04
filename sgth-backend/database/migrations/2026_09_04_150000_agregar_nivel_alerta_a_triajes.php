<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El nivel se guarda, no se recalcula al leer: la cola y el historial deben
     * mostrar lo que se valoró con las cifras de ese momento, y los umbrales
     * pueden cambiar cuando el personal médico los revise.
     */
    public function up(): void
    {
        Schema::table('triajes', function (Blueprint $table) {
            $table->string('nivel_alerta', 20)
                  ->default('normal')
                  ->after('observaciones_enfermera');

            // Qué constantes se salieron y cuánto, para no obligar a comparar
            // a ojo contra la tabla de rangos al abrir un triaje viejo.
            $table->json('hallazgos_alerta')
                  ->nullable()
                  ->after('nivel_alerta');

            $table->index('nivel_alerta');
        });
    }

    public function down(): void
    {
        Schema::table('triajes', function (Blueprint $table) {
            $table->dropIndex(['nivel_alerta']);
            $table->dropColumn(['nivel_alerta', 'hallazgos_alerta']);
        });
    }
};
