<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los avisos del préstamo de equipos (fase 2.4): el recordatorio antes de la
 * hora de devolución y el aviso de vencido salen una sola vez. El seguimiento
 * corre cada quince minutos, así que hay que anotar cuándo salió cada uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos_equipo', function (Blueprint $table) {
            $table->timestamp('recordatorio_enviado_en')->nullable();
            $table->timestamp('aviso_vencido_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prestamos_equipo', function (Blueprint $table) {
            $table->dropColumn(['recordatorio_enviado_en', 'aviso_vencido_en']);
        });
    }
};
