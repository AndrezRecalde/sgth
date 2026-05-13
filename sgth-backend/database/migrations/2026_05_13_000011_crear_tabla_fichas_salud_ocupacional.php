<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('fecha_evaluacion');
            $table->string('tipo_ficha', 50); 
            $table->string('aptitud', 50); 
            $table->text('restricciones')->nullable();
            $table->text('observaciones')->nullable();
            
            $table->foreignId('evaluador_id')->constrained('users');
            $table->foreignId('accidente_trabajo_id')->nullable()->constrained('accidentes_trabajo')->nullOnDelete();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('servidor_id');
            $table->index('accidente_trabajo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_salud_ocupacional');
    }
};
