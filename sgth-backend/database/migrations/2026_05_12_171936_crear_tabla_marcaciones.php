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
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            
            // Relación con el servidor importado
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            // Datos nativos del biométrico
            $table->timestamp('fecha_hora');
            $table->enum('tipo', ['entrada', 'salida']);
            $table->string('dispositivo_id')->nullable(); // ID del reloj físico (ej: ZKTeco-01)
            
            // Timestamps para control interno de cuándo se importó la data
            $table->timestamps();
            
            // NOTA: NO se incluyen softDeletes ya que es un registro de auditoría inmutable
            
            // Índices para agilizar el cálculo diario de asistencia
            $table->index(['servidor_id', 'fecha_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marcaciones');
    }
};
