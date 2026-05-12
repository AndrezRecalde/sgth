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
        Schema::create('handoffs_erp', function (Blueprint $table) {
            $table->id();
            
            $table->enum('tipo', [
                'nomina', 
                'viatico_compromiso', 
                'viatico_devengado', 
                'novedad_personal'
            ]);
            
            // ID dinámico que apunta a Nomina, Viatico u otra entidad según el tipo
            $table->unsignedBigInteger('referencia_id');
            
            $table->string('archivo_nombre');
            $table->string('archivo_ruta');
            $table->string('hash_integridad', 64); // SHA-256 produce 64 caracteres hex
            
            $table->foreignId('generado_por')->constrained('users');
            $table->timestamp('generado_en')->useCurrent();
            
            $table->timestamp('importado_erp_en')->nullable();
            
            // Registro inmutable, NO softDeletes
            $table->timestamps();
            
            // Índices útiles
            $table->index(['tipo', 'referencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handoffs_erp');
    }
};
