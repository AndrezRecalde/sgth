<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecedentes_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->cascadeOnDelete();
            
            $table->enum('tipo', ['quirurgico', 'patologico', 'traumatico', 'ginecologico', 'familiar', 'otro']);
            $table->text('descripcion');
            $table->unsignedSmallInteger('fecha_aproximada')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('historia_clinica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecedentes_paciente');
    }
};
