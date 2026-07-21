<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cumplimiento_normativa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('normativa_legal_sso_id')->constrained('normativa_legal_sso')->cascadeOnDelete();
            $table->string('periodo', 7); // 'YYYY' (año) o 'YYYY-MM' (mes)
            $table->string('estado', 20);
            // Referencia textual al medio de verificación (documento, acta, etc.).
            // Se sustituirá por un adjunto real (documentos_sso) cuando se implemente la Fase 9.
            $table->text('medio_verificacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->timestamps();

            $table->unique(['normativa_legal_sso_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cumplimiento_normativa');
    }
};
