<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase 9: adjuntos polimórficos para el módulo SSO (actas, evidencias, fotos).
        // Reemplaza el campo de texto libre 'medio_verificacion' de CumplimientoNormativa
        // y ProgramaDrogaSeguimiento; también disponible para InspeccionSso y CapacitacionSso.
        Schema::create('documentos_sso', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); // documentable_type, documentable_id (+ índice compuesto)
            $table->string('nombre');
            $table->string('ruta_archivo');
            $table->string('tipo_mime', 100);
            $table->unsignedInteger('tamano_bytes');
            $table->foreignId('subido_por')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_sso');
    }
};
