<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La orden que nace de la ficha ocupacional (fase 5 de
 * docs/diseno-examenes-medicos.md, 5.6): la evaluación periódica, de ingreso o
 * de retiro pide sus exámenes sin una consulta de por medio. La orden sale de
 * una consulta o de una ficha, una de dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_examen', function (Blueprint $table) {
            $table->foreignId('ficha_salud_ocupacional_id')
                  ->nullable()
                  ->after('consulta_medica_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->nullOnDelete();

            $table->index('ficha_salud_ocupacional_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_examen', function (Blueprint $table) {
            $table->dropIndex(['ficha_salud_ocupacional_id']);
            $table->dropConstrainedForeignId('ficha_salud_ocupacional_id');
        });
    }
};
