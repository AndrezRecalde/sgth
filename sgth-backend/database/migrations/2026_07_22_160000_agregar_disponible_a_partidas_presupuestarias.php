<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hint mínimo viable de disponibilidad presupuestaria: no es un saldo
     * ni un libro de consumos (eso requeriría revertir al anular, trackear
     * compromisos, etc. — fuera de alcance de esta fase). Es un booleano
     * que el área presupuestaria mantiene manualmente para indicar si la
     * partida tiene fondos verificados. Default false: la disponibilidad
     * debe verificarse explícitamente (Art. 105 LOSEP), no asumirse.
     */
    public function up(): void
    {
        Schema::table('partidas_presupuestarias', function (Blueprint $table) {
            $table->boolean('disponible')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('partidas_presupuestarias', function (Blueprint $table) {
            $table->dropColumn('disponible');
        });
    }
};
