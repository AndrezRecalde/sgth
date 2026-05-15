<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enfermedades_catastroficas_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            
            $table->string('tipo_enfermedad');
            $table->string('codigo_cie10')->nullable();
            
            $table->string('certificado_ruta')->nullable();
            $table->string('certificado_nombre_archivo')->nullable();
            $table->date('fecha_diagnostico')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enfermedades_catastroficas_servidor');
    }
};
