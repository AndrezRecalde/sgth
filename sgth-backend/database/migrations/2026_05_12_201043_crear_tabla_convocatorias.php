<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('puesto_id')->constrained('puestos')->onDelete('restrict');
            
            $table->string('codigo', 50)->unique(); // Ej: CNV-2026-001
            $table->string('titulo');
            $table->text('descripcion');
            $table->json('bases_concurso')->nullable();
            
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            
            // Estados LOSEP para convocatorias
            $table->enum('estado', ['publicada', 'en_evaluacion', 'finalizada', 'cancelada'])->default('publicada');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('estado');
            $table->index('puesto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatorias');
    }
};
