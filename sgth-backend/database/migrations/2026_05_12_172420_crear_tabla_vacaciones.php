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
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            
            // Decimal para permitir medios días o fracciones en caso de compensaciones
            $table->decimal('dias_solicitados', 5, 2);
            
            // Importante para saber qué motor de descuento usar (LOSEP vs CT)
            $table->enum('tipo_dias', ['habiles', 'calendario']);
            
            // Flujo de estados
            $table->enum('estado', [
                'pendiente',
                'aprobada',
                'rechazada',
                'gozada' // Cuando ya pasaron las fechas de la vacación
            ])->default('pendiente');
            
            // Auditoría
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para agilizar filtros de historial y calendarios
            $table->index(['servidor_id', 'estado']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};
