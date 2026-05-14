<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('expediente_id')->constrained('expedientes_electronicos')->onDelete('restrict');
            
            $table->string('codigo', 50)->unique(); // Ej: TRM-2026-0001
            $table->string('asunto');
            
            $table->enum('estado', ['iniciado', 'en_proceso', 'finalizado', 'archivado'])->default('iniciado');
            
            // El servidor que inicia o está relacionado con el trámite
            $table->foreignId('solicitante_id')->nullable()->constrained('servidores')->onDelete('set null');
            // Quien está a cargo actualmente
            $table->foreignId('responsable_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('estado');
            $table->index('expediente_id');
            $table->index('solicitante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};
