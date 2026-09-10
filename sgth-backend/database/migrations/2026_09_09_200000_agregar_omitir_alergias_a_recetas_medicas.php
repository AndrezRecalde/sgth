<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja al médico ocultar las alergias en el impreso de una receta concreta.
 *
 * La receta se imprime y sale del dispensario: la despachan compañeros de
 * trabajo del paciente y, fuera, quien esté en el mostrador. Casi siempre
 * imprimir las alergias protege —quien dispensa se entera de a qué reacciona—,
 * y por eso el valor por defecto es imprimirlas.
 *
 * Pero hay alergias que delatan el diagnóstico: a un antirretroviral, a un
 * citostático, a metadona, a un antipsicótico. Ahí el papel filtraría
 * exactamente lo que se protegió al dejar el CIE-10 fuera del impreso, solo que
 * por la puerta de al lado. Quién puede juzgar eso es el médico y nadie más, y
 * solo caso por caso; de ahí que sea un interruptor por receta y no un ajuste
 * del sistema.
 *
 * Es un dato de impresión, no clínico: no cambia lo que consta en la historia
 * clínica ni lo que ve el dispensario en pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->boolean('omitir_alergias')
                  ->default(false)
                  ->after('indicaciones_generales');
        });
    }

    public function down(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->dropColumn('omitir_alergias');
        });
    }
};
