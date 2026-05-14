<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->restrictOnDelete();
            $table->foreignId('medico_id')->constrained('users');
            $table->date('fecha_consulta');
            $table->time('hora_consulta');
            
            // Campos de texto largo preparados para cifrado en el modelo
            $table->text('motivo_consulta')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('diagnostico_detallado')->nullable();
            $table->text('plan_tratamiento')->nullable();
            $table->text('notas_medico')->nullable();
            
            // Diagnóstico CIE-10 (No se cifra para estadísticas)
            $table->string('diagnostico_cie10', 20)->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('historia_clinica_id');
            $table->index('medico_id');
            $table->index('fecha_consulta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_medicas');
    }
};
