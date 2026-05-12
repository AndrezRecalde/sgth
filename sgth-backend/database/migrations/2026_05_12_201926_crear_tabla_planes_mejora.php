<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_mejora', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('resultado_id')->unique()->constrained('resultados_evaluacion')->onDelete('cascade');
            
            $table->text('brechas_identificadas'); // Retroalimenta al Módulo 15 de Capacitación
            $table->text('acciones_mejora');
            
            $table->date('fecha_cumplimiento');
            $table->enum('estado', ['pendiente', 'en_progreso', 'cumplido', 'incumplido'])->default('pendiente');
            
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
        Schema::dropIfExists('planes_mejora');
    }
};
