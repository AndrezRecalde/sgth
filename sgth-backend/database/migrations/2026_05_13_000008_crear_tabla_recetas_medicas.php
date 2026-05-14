<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_medica_id')->constrained('consultas_medicas')->restrictOnDelete();
            $table->date('fecha_emision');
            $table->string('estado', 50)->default('emitida');
            $table->text('indicaciones_generales')->nullable();
            
            $table->foreignId('despachado_por')->nullable()->constrained('users');
            $table->timestamp('despachado_en')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('consulta_medica_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas_medicas');
    }
};
