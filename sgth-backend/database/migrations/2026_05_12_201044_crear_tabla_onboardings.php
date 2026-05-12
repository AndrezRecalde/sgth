<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            
            // Vinculación al concurso
            $table->foreignId('postulante_id')->unique()->constrained('postulantes')->onDelete('restrict');
            
            // Vinculación al expediente (se llena cuando se consolida la contratación)
            $table->foreignId('servidor_id')->nullable()->unique()->constrained('servidores')->onDelete('set null');
            
            // Checklist básico
            $table->boolean('documentacion_entregada')->default(false);
            $table->boolean('induccion_completada')->default(false);
            $table->boolean('contrato_firmado')->default(false);
            
            $table->text('observaciones')->nullable();
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboardings');
    }
};
