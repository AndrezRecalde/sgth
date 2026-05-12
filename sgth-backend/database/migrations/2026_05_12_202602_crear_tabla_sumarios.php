<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumarios', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('restrict');
            
            $table->text('motivo'); // Razón de apertura del sumario
            
            $table->enum('estado', [
                'abierto', 
                'en_instruccion', 
                'en_prueba', 
                'con_informe', 
                'resuelto', 
                'apelado', 
                'cerrado'
            ])->default('abierto');
            
            // Control de plazos procesales legales
            $table->date('fecha_apertura');
            
            $table->boolean('notificado_sn')->default(false);
            $table->date('fecha_notificacion')->nullable(); // Debe ocurrir max 3 días hábiles desde apertura
            
            $table->date('fecha_termino_prueba')->nullable(); // Dura 5 días hábiles desde instrucción
            $table->date('fecha_informe')->nullable(); // Max 3 días hábiles después del término de prueba
            $table->date('fecha_resolucion')->nullable(); // Max 10 días hábiles desde el informe
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumarios');
    }
};
