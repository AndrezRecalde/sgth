<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se van los dos campos que dieron origen a todo el problema.
 *
 * `inventario_medicinas` guardaba un solo `lote` y una sola `fecha_caducidad`
 * para todas las existencias del medicamento, y cada adquisición los
 * sobrescribía. Desde el control por lotes ni se escriben ni se leen: la
 * caducidad vive en cada lote, que es lo único que caduca.
 *
 * Se retiran en vez de dejarlos ahí porque una columna que parece autorizada y
 * no lo es es peor que ninguna: el siguiente que la vea la creerá.
 *
 * No hay dato que perder: lo que contenían era el valor de la última entrada, y
 * esa misma información está en el lote correspondiente y en
 * `items_adquisicion`. El `down()` devuelve las columnas vacías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_medicinas', function (Blueprint $table) {
            $table->dropColumn(['lote', 'fecha_caducidad']);
        });
    }

    public function down(): void
    {
        Schema::table('inventario_medicinas', function (Blueprint $table) {
            $table->date('fecha_caducidad')->nullable();
            $table->string('lote')->nullable();
        });
    }
};
