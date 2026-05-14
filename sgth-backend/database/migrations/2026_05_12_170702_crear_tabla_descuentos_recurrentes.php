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
        Schema::create('descuentos_recurrentes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            $table->foreignId('concepto_nomina_id')->constrained('conceptos_nomina')->onDelete('restrict');
            
            $table->decimal('valor_cuota', 10, 2);
            $table->integer('numero_cuotas_total');
            $table->integer('numero_cuotas_pagadas')->default(0);
            
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable(); // Se calcula de forma automática
            
            $table->string('referencia_externa')->nullable(); // Ej: Número de préstamo IESS
            
            $table->enum('estado', [
                'activo',
                'completado',
                'suspendido'
            ])->default('activo');
            
            $table->text('observacion')->nullable();
            
            $table->foreignId('registrado_por')->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para agilizar la búsqueda de descuentos activos durante el cálculo de nómina
            $table->index(['estado', 'fecha_inicio']);
            $table->index('servidor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descuentos_recurrentes');
    }
};
