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
        Schema::create('puestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('denominacion');
            
            $table->foreignId('unidad_administrativa_id')
                  ->constrained('unidades_administrativas')
                  ->restrictOnDelete();
            
            $table->string('grupo_ocupacional');
            $table->unsignedTinyInteger('grado_rmu');
            $table->decimal('rmu', 10, 2);
            $table->boolean('es_jefe')->default(false); // Base del método esJefeDeServidor
            $table->unsignedTinyInteger('nivel');
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para optimizar consultas comunes
            $table->index('estado');
            $table->index('es_jefe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
