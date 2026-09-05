<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dos columnas que ya no dicen nada.
     *
     * `diagnostico_cie10` es un `string(20)` de cuando el diagnóstico se
     * anotaba a mano. Se sustituyó por `diagnostico_cie10_id`, que apunta al
     * catálogo, y desde entonces nunca se escribió: está a nulo en todas las
     * filas. Su única mención en el código era una consulta de Autoservicio que
     * además nombraba otras tres columnas inexistentes y devolvía un 500; esa
     * consulta se corrigió y ahora lee el diagnóstico del CIE-10 relacionado.
     *
     * `estado` es un booleano que el alta escribía siempre a `true` y que nadie
     * consultaba jamás. Lo que de verdad marca el ciclo de vida de una consulta
     * es el borrado en blando, y las correcciones se guardan versionadas.
     *
     * Se comprobó antes de tirarlas: cero filas con `diagnostico_cie10`, y
     * `estado` a `true` en todas.
     */
    public function up(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->dropColumn(['diagnostico_cie10', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->string('diagnostico_cie10', 20)->nullable();
            $table->boolean('estado')->default(true);
        });
    }
};
