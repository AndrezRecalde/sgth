<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El problema frecuente con que se abrió cada ticket (respuestas de TIC del
 * 2026-11-05, pregunta 12): de ahí sale el reporte de uso que TIC revisa cada
 * trimestre —los más pedidos, los que nadie usa y cuántos entran por «Otro
 * problema»—. Sin problema, el ticket se abrió con el formulario libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'problema_frecuente_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('problema_frecuente_id')->nullable()->after('categoria_id')->constrained('problemas_frecuentes');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('problema_frecuente_id');
        });
    }
};
