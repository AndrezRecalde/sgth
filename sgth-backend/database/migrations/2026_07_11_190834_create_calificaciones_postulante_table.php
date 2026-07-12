<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seleccion_calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')
                  ->constrained('postulantes')
                  ->cascadeOnDelete();
            $table->foreignId('criterio_id')
                  ->constrained('seleccion_criterios')
                  ->cascadeOnDelete();
            $table->foreignId('opcion_id')
                  ->nullable()
                  ->constrained('seleccion_opciones')
                  ->nullOnDelete();
            $table->decimal('valor_numerico', 5, 2)->nullable();
            $table->decimal('puntaje_obtenido', 5, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')
                  ->constrained('users');
            $table->timestamps();

            $table->unique(['postulante_id', 'criterio_id']);
            $table->index('postulante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seleccion_calificaciones');
    }
};
