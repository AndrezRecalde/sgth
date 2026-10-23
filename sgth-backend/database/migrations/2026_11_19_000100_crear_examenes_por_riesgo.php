<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué exámenes corresponden a cada factor de riesgo del puesto (fase 5 de
 * docs/diseno-examenes-medicos.md, D6): ruido → audiometría, polvos →
 * espirometría. Lo define el médico ocupacional o la administración en el
 * SGTH; la ficha ocupacional lo usa para proponer la orden de la evaluación.
 *
 * El factor es el del catálogo oficial del MSP (App\Catalogos\FactoresRiesgoMsp),
 * que se identifica por su categoría y su nombre literal. Nace vacío: el
 * Dispensario no dio la lista y la arma el médico en la pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riesgo_examenes', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 20);
            $table->string('factor', 150);
            // Restrict: un examen del catálogo se retira, no se borra.
            $table->foreignId('catalogo_examen_id')
                  ->constrained('catalogo_examenes')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['categoria', 'factor', 'catalogo_examen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_examenes');
    }
};
