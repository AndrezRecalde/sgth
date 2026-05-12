<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados_evaluacion', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('evaluacion_id')->constrained('evaluaciones_desempeno')->onDelete('restrict');
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('restrict');
            $table->foreignId('evaluador_id')->constrained('users')->onDelete('restrict'); // Jefe inmediato
            
            $table->decimal('calificacion_cuantitativa', 5, 2)->default(0.00); // 0 a 100
            
            // Escala MRL
            $table->enum('calificacion_cualitativa', [
                'excelente',      // 91 - 100
                'muy_bueno',      // 81 - 90
                'satisfactorio',  // 71 - 80
                'regular',        // 61 - 70 (2 veces = destitución)
                'insuficiente'    // <= 60 (destitución inmediata)
            ])->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamp('retroalimentacion_fecha')->nullable();
            
            $table->enum('apelacion_estado', ['sin_apelacion', 'en_revision', 'ratificada', 'modificada'])->default('sin_apelacion');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['evaluacion_id', 'servidor_id'], 'uq_resultado_evaluacion_servidor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados_evaluacion');
    }
};
