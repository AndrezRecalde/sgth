<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados_institucionales', function (Blueprint $table) {
            $table->id();
            
            $table->date('fecha')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->unsignedTinyInteger('dia')->nullable();
            $table->string('descripcion');
            $table->boolean('es_nacional')->default(true);
            $table->boolean('es_movil')->default(false);
            
            $table->timestamps();
            
            // Índices para optimizar las consultas de la scope
            $table->index(['es_movil', 'mes', 'dia']);
            $table->index(['es_movil', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados_institucionales');
    }
};
