<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alergias_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->cascadeOnDelete();
            
            $table->enum('tipo', ['medicamento', 'alimento', 'ambiental', 'otro']);
            $table->string('descripcion');
            $table->enum('severidad', ['leve', 'moderada', 'grave']);
            $table->text('observacion')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('historia_clinica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alergias_paciente');
    }
};
