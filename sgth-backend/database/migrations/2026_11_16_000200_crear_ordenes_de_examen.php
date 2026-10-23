<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La orden de exámenes: lo que el médico pide, el papel que el paciente lleva
 * al laboratorio.
 *
 * Hasta ahora el Dispensario guardaba resultados sueltos, pero no qué se
 * había pedido: no había manera de saber qué exámenes nunca volvieron. La
 * orden sigue el molde de la receta (folio con bloqueo, anulación con motivo,
 * autoría) y se separa del resultado, que se le colgará en la fase 3.
 *
 * Sin borrado: una orden equivocada se anula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_examen', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas');
            $table->foreignId('consulta_medica_id')->nullable()->constrained('consultas_medicas');
            $table->foreignId('solicitado_por')->constrained('users');
            $table->date('fecha_solicitud');
            // El diagnóstico presuntivo que justifica la orden.
            $table->foreignId('diagnostico_cie10_id')
                  ->nullable()
                  ->constrained('diagnosticos_cie10')
                  ->nullOnDelete();
            $table->string('prioridad', 10)->default('rutina');
            $table->text('indicaciones')->nullable();
            $table->string('laboratorio_destino', 150)->nullable();
            // Constancia de que el paciente firmó en papel el consentimiento de
            // un examen sensible (D8). El papel no se sube.
            $table->boolean('consentimiento_firmado')->default(false);
            $table->string('estado', 20)->default('emitida');
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index('historia_clinica_id');
            $table->index('consulta_medica_id');
            $table->index(['estado', 'fecha_solicitud']);
        });

        Schema::create('orden_examen_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_examen_id')
                  ->constrained('ordenes_examen')
                  ->cascadeOnDelete();
            // Siempre del catálogo (D4). Restrict: un examen se retira, no se borra.
            $table->foreignId('catalogo_examen_id')
                  ->constrained('catalogo_examenes')
                  ->restrictOnDelete();
            // El dato que completa el médico en los exámenes que lo llevan:
            // «75 g», «3 muestras», «derecho, antebrazo».
            $table->string('nota', 100)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('motivo_no_realizado', 255)->nullable();
            $table->timestamps();

            $table->unique(['orden_examen_id', 'catalogo_examen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_examen_items');
        Schema::dropIfExists('ordenes_examen');
    }
};
