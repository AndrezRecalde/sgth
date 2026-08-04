<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del vínculo que Talento Humano edita mientras la acción está en
 * borrador y que se materializan en el ContratoServidor al registrarla.
 *
 * Antes vivían solo en el contrato, así que no había forma de fijarlos —ni de
 * revisarlos— durante la aprobación: el contrato nacía con los defaults y
 * había que corregirlo después, por fuera de la acción de personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('numero_contrato', 100)
                  ->nullable()
                  ->after('remuneracion_propuesta');

            $table->foreignId('partida_presupuestaria_id')
                  ->nullable()
                  ->after('numero_contrato')
                  ->constrained('partidas_presupuestarias')
                  ->nullOnDelete();

            // Marcación biométrica. Nullable a propósito: null significa "no
            // se pronunció esta acción" y deja que el contrato conserve su
            // propio default, distinto de un false explícito de TH.
            $table->boolean('puede_marcar')
                  ->nullable()
                  ->after('partida_presupuestaria_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partida_presupuestaria_id');
            $table->dropColumn(['numero_contrato', 'puede_marcar']);
        });
    }
};
