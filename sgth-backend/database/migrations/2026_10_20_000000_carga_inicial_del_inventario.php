<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que necesita la carga del Excel de TIC (docs/diseno-subsistema-tic.md,
 * sección 7; fase 1.4) en los mantenimientos:
 *
 * - `soporte_anterior`: el número de soporte del sistema antiguo (D1), como
 *   ya tiene la custodia.
 * - `fecha_mantenimiento` admite vacío: el Excel dice qué se hizo y con qué
 *   soporte, pero no cuándo. Inventar una fecha sería peor que no tenerla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mantenimientos_bien', function (Blueprint $table) {
            $table->string('soporte_anterior', 30)->nullable();
            $table->date('fecha_mantenimiento')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mantenimientos_bien', function (Blueprint $table) {
            $table->dropColumn('soporte_anterior');
        });
    }
};
