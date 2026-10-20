<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La salida, sin montos calculados (2026-10-20): el SGTH es informativo.
 *
 * - Las vacaciones no gozadas se confirman en días: su valor —la remuneración
 *   ÷ 30 por día— lo calcula Financiero.
 * - La indemnización se registra como lo que pagó Financiero, con su
 *   resolución; el tope del Mandato 2 lo aplica quien la calcula.
 *
 * El monto de la indemnización se queda: es el dato del que depende la
 * prohibición de reingreso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desvinculaciones', function (Blueprint $table) {
            $table->dropColumn(['vacaciones_remuneracion', 'vacaciones_monto', 'indemnizacion_tope']);
        });
    }

    public function down(): void
    {
        Schema::table('desvinculaciones', function (Blueprint $table) {
            $table->decimal('vacaciones_remuneracion', 12, 2)->nullable()->after('vacaciones_dias');
            $table->decimal('vacaciones_monto', 12, 2)->nullable()->after('vacaciones_remuneracion');
            $table->decimal('indemnizacion_tope', 12, 2)->nullable()->after('indemnizacion_resolucion');
        });
    }
};
