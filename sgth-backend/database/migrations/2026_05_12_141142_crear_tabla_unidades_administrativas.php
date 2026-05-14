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
        Schema::create('unidades_administrativas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            
            // Relación jerárquica (self-referential)
            $table->foreignId('unidad_padre_id')
                  ->nullable()
                  ->constrained('unidades_administrativas')
                  ->restrictOnDelete();
                  
            $table->unsignedTinyInteger('nivel');
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para optimizar las consultas del organigrama
            $table->index('nivel');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_administrativas');
    }
};
