<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_receta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_medica_id')->constrained('recetas_medicas')->cascadeOnDelete();
            $table->foreignId('inventario_medicina_id')->constrained('inventario_medicinas')->restrictOnDelete();
            $table->integer('cantidad_prescrita');
            $table->integer('cantidad_despachada')->default(0);
            $table->string('dosis');
            $table->string('frecuencia');
            $table->string('duracion');
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('receta_medica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_receta');
    }
};
