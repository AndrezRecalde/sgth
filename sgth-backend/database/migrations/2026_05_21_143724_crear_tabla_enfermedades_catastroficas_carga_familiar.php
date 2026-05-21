<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enfermedades_catastroficas_carga_familiar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_familiar_id')
                  ->constrained('cargas_familiares')
                  ->cascadeOnDelete();

            $table->string('tipo_enfermedad', 150);
            $table->string('codigo_cie10', 10)->nullable();
            $table->string('certificado_ruta')->nullable();
            $table->string('certificado_nombre_archivo')->nullable();
            $table->date('fecha_diagnostico')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enfermedades_catastroficas_carga_familiar');
    }
};