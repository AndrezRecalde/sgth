<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visto bueno: trámite de terminación con justa causa del contrato de un
 * obrero, ante el Inspector del Trabajo (Art. 172 y 183 del Código del
 * Trabajo).
 *
 * Va en tabla propia y no dentro de 'sumarios' porque el procedimiento es otro:
 * lo resuelve una autoridad externa (Ministerio del Trabajo) y no la autoridad
 * nominadora, los plazos son distintos, y el resultado es binario
 * (concedido/negado) en vez de una escala de sanciones. Meterlo en 'sumarios'
 * dejaría la mitad de las columnas nulas según el régimen del servidor.
 *
 * No se modela la suspensión del trabajador durante el trámite: confirmado con
 * Talento Humano que no se usa en la práctica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vistos_buenos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->string('causal', 40);
            $table->string('estado', 20)->default('solicitado');

            // Datos del trámite ante el Ministerio del Trabajo. Nulos hasta
            // que la institución presenta la solicitud y recibe el número.
            $table->string('numero_tramite_mdt', 50)->nullable();
            $table->string('inspectoria', 150)->nullable();
            $table->string('inspector_nombre', 150)->nullable();

            $table->date('fecha_solicitud');
            $table->date('fecha_notificacion')->nullable();
            $table->date('fecha_resolucion')->nullable();

            $table->text('hechos');
            $table->text('resolucion_detalle')->nullable();
            $table->string('documento_respaldo')->nullable();

            // Cesación de funciones generada cuando el Inspector concede el
            // visto bueno. Nula mientras el trámite no se resuelva a favor.
            $table->foreignId('movimiento_personal_id')
                  ->nullable()
                  ->constrained('movimientos_personal')
                  ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_solicitud']);
            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vistos_buenos');
    }
};
