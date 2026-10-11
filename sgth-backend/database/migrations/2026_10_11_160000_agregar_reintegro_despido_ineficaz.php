<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El reintegro del obrero por despido ineficaz (Código del Trabajo, Arts.
 * 195.1 a 195.3; diseño de Acciones de Personal, 9.15; fase 4.5b). Es la
 * restitución de la fase 2.6 con las reglas del Código.
 *
 * - `fecha_providencia`: el juez ordena el reintegro inmediato en la
 *   providencia que califica la demanda, como medida cautelar (Art. 195.2), y
 *   el empleador lo cumple ahí, sin esperar la sentencia. Con la providencia,
 *   la sentencia y su ejecutoría quedan vacías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_providencia')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('fecha_providencia');
        });
    }
};
