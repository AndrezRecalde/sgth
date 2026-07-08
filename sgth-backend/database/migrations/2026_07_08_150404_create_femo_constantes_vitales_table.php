<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_constantes_vitales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->decimal('temperatura_c', 4, 1)->nullable();
            $table->integer('presion_sistolica')->nullable();
            $table->integer('presion_diastolica')->nullable();
            $table->integer('frecuencia_cardiaca')->nullable();
            $table->integer('frecuencia_respiratoria')->nullable();
            $table->decimal('saturacion_oxigeno', 4, 1)->nullable();
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->decimal('talla_cm', 5, 2)->nullable();
            $table->decimal('imc', 4, 2)->nullable();
            $table->decimal('glucosa', 5, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_constantes_vitales');
    }
};
