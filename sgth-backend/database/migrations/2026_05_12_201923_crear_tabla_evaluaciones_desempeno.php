<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_desempeno', function (Blueprint $table) {
            $table->id();
            
            $table->string('periodo', 20)->unique(); // Ej: 2026-S1
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            
            // Workflow de la Norma Técnica
            $table->enum('estado', [
                'inicio_periodo', 
                'en_evaluacion', 
                'retroalimentacion', 
                'calificacion_final', 
                'apelacion', 
                'cerrada'
            ])->default('inicio_periodo');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_desempeno');
    }
};
