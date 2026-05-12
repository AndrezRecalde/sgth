<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subrogaciones', function (Blueprint $table) {
            $table->id();
            
            $table->enum('tipo', ['subrogacion', 'encargo']);
            $table->foreignId('servidor_subrogante_id')->constrained('servidores');
            $table->foreignId('servidor_subrogado_id')->nullable()->constrained('servidores');
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas');
            $table->foreignId('puesto_subrogado_id')->constrained('puestos');
            
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            
            $table->enum('motivo', [
                'vacaciones',
                'comision_servicios',
                'enfermedad',
                'licencia',
                'encargo_vacante',
                'otro'
            ]);
            
            $table->string('resolucion_numero')->nullable();
            $table->string('documento_respaldo')->nullable();
            $table->enum('estado', ['activa', 'finalizada', 'cancelada'])->default('activa');
            $table->text('observacion')->nullable();
            
            $table->foreignId('registrado_por')->constrained('users');
            
            $table->timestamps();
            // SIN softDeletes para garantizar un historial inmutable
            
            // Índices de optimización requeridos para los policies
            $table->index(['servidor_subrogante_id', 'estado', 'fecha_inicio', 'fecha_fin'], 'idx_subrogante_estado_fechas');
            $table->index(['unidad_administrativa_id', 'estado'], 'idx_unidad_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subrogaciones');
    }
};
