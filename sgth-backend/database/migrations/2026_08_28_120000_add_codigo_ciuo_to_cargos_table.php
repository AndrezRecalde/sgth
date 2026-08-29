<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El código CIUO clasifica al PUESTO, no a la evaluación médica.
 *
 * La ficha FEMO lo pedía escrito a mano en cada evaluación, de modo que el
 * mismo cargo terminaba con códigos distintos según quién llenara la ficha.
 * Al vivir en el cargo se define una vez y lo heredan todas las fichas.
 *
 * CIUO-08 (adaptación ecuatoriana del INEC) usa hasta cuatro dígitos; se deja
 * `string` y no entero porque los códigos son significativos y pueden llevar
 * ceros a la izquierda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->string('codigo_ciuo', 10)
                ->nullable()
                ->after('denominacion_generica');
        });
    }

    public function down(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->dropColumn('codigo_ciuo');
        });
    }
};
