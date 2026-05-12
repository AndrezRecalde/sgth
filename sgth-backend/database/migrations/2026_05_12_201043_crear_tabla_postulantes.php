<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('convocatoria_id')->constrained('convocatorias')->onDelete('restrict');
            
            $table->string('cedula', 10);
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('correo');
            $table->string('telefono', 20)->nullable();
            $table->string('cv_ruta')->nullable();
            
            $table->enum('estado', ['postulado', 'en_proceso', 'aprobado', 'reprobado', 'descalificado'])->default('postulado');
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->unique(['convocatoria_id', 'cedula'], 'uq_postulante_convocatoria');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulantes');
    }
};
