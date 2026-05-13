<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('users');
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado', 50)->default('programada');
            $table->string('motivo_solicitud')->nullable();
            
            $table->boolean('estado_registro')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['medico_id', 'fecha']);
            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendas_medicas');
    }
};
