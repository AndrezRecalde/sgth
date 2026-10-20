<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las novedades para Financiero, sin montos (2026-10-20): el SGTH es
 * informativo y no calcula la nómina. Cada novedad dice lo que pasó —las
 * fechas, los días y, en el detalle, lo que fija el acto—; los valores los
 * calcula Financiero en su sistema. Se van la base y el monto de referencia de
 * lo entregado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novedades_nomina', function (Blueprint $table) {
            $table->dropColumn(['base', 'monto']);
        });
    }

    public function down(): void
    {
        Schema::table('novedades_nomina', function (Blueprint $table) {
            $table->decimal('base', 12, 2)->nullable()->after('dias');
            $table->decimal('monto', 12, 2)->nullable()->after('base');
        });
    }
};
