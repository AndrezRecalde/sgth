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
        Schema::create('contratos_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            
            $table->enum('tipo_nombramiento', [
                'nombramiento_permanente',
                'nombramiento_provisional',
                'servicios_ocasionales',
                'libre_nombramiento_remocion',
                'codigo_trabajo',
                'servicios_profesionales'
            ]);
            $table->string('numero_contrato')->nullable();
            
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas');
            $table->foreignId('puesto_id')->constrained('puestos');
            
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            
            $table->string('resolucion_numero')->nullable();
            $table->string('documento_ruta')->nullable();
            
            $table->string('codigo_marcacion', 10)->nullable();
            
            $table->enum('estado', ['vigente', 'terminado', 'cancelado'])->default('vigente');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['servidor_id', 'estado']);
            $table->index('codigo_marcacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos_servidor');
    }
};
