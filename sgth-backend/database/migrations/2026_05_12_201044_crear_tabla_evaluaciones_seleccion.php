<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_seleccion', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('postulante_id')->unique()->constrained('postulantes')->onDelete('cascade');
            
            // Ponderación LOSEP: Méritos 40%
            $table->decimal('puntaje_meritos', 5, 2)->default(0.00); // 0 a 40
            
            // Ponderación LOSEP: Oposición 60% (pruebas + entrevista)
            $table->decimal('puntaje_oposicion', 5, 2)->default(0.00); // 0 a 60
            
            $table->decimal('puntaje_total', 5, 2)->default(0.00); // meritos + oposicion
            
            $table->text('observaciones')->nullable();
            
            $table->foreignId('evaluador_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_seleccion');
    }
};
