<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada movimiento dice de qué lote salió o a qué lote entró.
 *
 * Nullable y sin tocar el histórico a propósito: el kardex es inmutable, y
 * asignarle un lote a un movimiento pasado sería inventarlo. Los movimientos
 * anteriores a esta migración se quedan sin lote, que es la verdad —nadie lo
 * anotó—, y solo los nuevos lo llevan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario_med', function (Blueprint $table) {
            $table->foreignId('lote_id')
                  ->nullable()
                  ->after('inventario_medicina_id')
                  ->constrained('lotes_medicina')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario_med', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });
    }
};
