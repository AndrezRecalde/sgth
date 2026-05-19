<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_medica_id')->constrained('agendas_medicas')->cascadeOnDelete();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->restrictOnDelete();
            $table->foreignId('enfermera_id')->constrained('users')->restrictOnDelete();
            
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->decimal('talla_cm', 5, 2)->nullable();
            $table->decimal('imc', 5, 2)->nullable();
            
            $table->decimal('temperatura_c', 4, 2)->nullable();
            $table->smallInteger('presion_sistolica')->nullable();
            $table->smallInteger('presion_diastolica')->nullable();
            $table->smallInteger('frecuencia_cardiaca')->nullable();
            $table->smallInteger('frecuencia_respiratoria')->nullable();
            $table->decimal('saturacion_oxigeno', 4, 1)->nullable();
            $table->decimal('glucosa', 6, 2)->nullable();
            
            $table->text('observaciones_enfermera')->nullable();
            $table->timestamp('registrado_en')->useCurrent();
            
            $table->timestamps();
            // Sin SoftDeletes ya que es un registro clínico inmutable
            
            $table->index('agenda_medica_id');
            $table->index('historia_clinica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triajes');
    }
};
