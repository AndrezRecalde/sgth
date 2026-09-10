<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invierte la política del impreso: las alergias dejan de imprimirse salvo que
 * el médico lo pida.
 *
 * Cuando se añadió `omitir_alergias` nacía en `false` —se imprimían siempre y
 * ocultarlas era la excepción—, porque en el mostrador de una farmacia externa
 * la alergia protege al paciente en el momento de la entrega. La institución
 * decide priorizar la minimización del dato: la receta pasa por manos de
 * compañeros de trabajo del paciente, y una alergia sigue siendo dato de salud
 * aunque revele menos que un diagnóstico.
 *
 * Solo cambia el valor por defecto, y por tanto solo afecta a las recetas
 * futuras. Las ya emitidas conservan el suyo A PROPÓSITO: su copia impresa está
 * en manos del paciente, y reimprimir un folio no puede devolver un documento
 * distinto del que se entregó.
 *
 * El impreso nunca afirma que el paciente no tenga alergias: dice que se
 * consulten en el dispensario. Esa distinción es la que impide que ocultar se
 * convierta en un peligro clínico, y no cambia aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->boolean('omitir_alergias')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->boolean('omitir_alergias')->default(false)->change();
        });
    }
};
