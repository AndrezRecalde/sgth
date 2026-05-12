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
        Schema::create('permisos_servidor', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            $table->enum('tipo', ['personal', 'oficial', 'enfermedad', 'calamidad']);
            
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            
            // Obligatorio para tipo oficial, confidencial para tipo personal
            $table->text('observacion')->nullable();
            
            // Flujo de estados
            $table->enum('estado', [
                'pendiente',            // Inicial al crear
                'activo',               // Confirmado por recepción
                'anulado',              // Jefe lo anula antes de recepción
                'rechazado',            // Recepción rechaza el documento
                'falta_injustificada',  // Pasan 72h y el job lo marca
                'validado_trabajo_social' // Para permisos médicos/calamidad
            ])->default('pendiente');
            
            $table->string('folio')->unique()->nullable(); // Formato: PER-2026-00045
            
            // Auditoría de flujos
            $table->foreignId('confirmado_por')->nullable()->constrained('users'); // Recepción
            $table->timestamp('confirmado_en')->nullable();
            
            $table->foreignId('validado_ts_por')->nullable()->constrained('users'); // Trabajo Social
            $table->timestamp('validado_ts_en')->nullable();
            
            $table->foreignId('anulado_por')->nullable()->constrained('users'); // Jefe inmediato
            $table->timestamp('anulado_en')->nullable();
            
            // Regla crítica de negocio: 72 horas laborables
            $table->timestamp('vence_en');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para listados de jefes y job automático
            $table->index(['servidor_id', 'estado']);
            $table->index('vence_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permisos_servidor');
    }
};
