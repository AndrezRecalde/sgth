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
        Schema::create('detalle_nomina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomina_id')->constrained('nominas')->onDelete('cascade');
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            $table->foreignId('concepto_nomina_id')->constrained('conceptos_nomina')->onDelete('restrict');
            $table->decimal('valor', 12, 2);
            $table->string('observacion')->nullable();
            
            // Registro inmutable, NO softDeletes
            $table->timestamps();
            
            // Índices para optimizar consultas comunes
            $table->index(['nomina_id', 'servidor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_nomina');
    }
};
