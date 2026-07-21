<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_drogas_seguimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_droga_actividad_id')
                ->constrained('programa_drogas_actividades')
                ->cascadeOnDelete();
            $table->string('periodo', 7); // 'YYYY' (año) o 'YYYY-MM' (mes)
            $table->string('estado', 20)->default('pendiente');
            $table->date('fecha_ejecucion')->nullable();
            // Referencia textual al medio de verificación (documento, acta, evidencia).
            // Se sustituirá por un adjunto real (documentos_sso) cuando se implemente la Fase 9.
            $table->text('medio_verificacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->timestamps();

            $table->unique(['programa_droga_actividad_id', 'periodo'], 'programa_drogas_seguimiento_actividad_periodo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_drogas_seguimiento');
    }
};
