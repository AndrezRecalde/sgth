<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_constantes_vitales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')
                ->unique()
                ->constrained('solicitudes_certificacion_medica')
                ->cascadeOnDelete();
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
            // Sin SoftDeletes: registro clínico inmutable (mismo criterio que `triajes`)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_constantes_vitales');
    }
};
