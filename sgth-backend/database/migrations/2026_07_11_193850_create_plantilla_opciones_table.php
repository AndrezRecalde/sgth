<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_criterio_id')
                  ->constrained('plantilla_criterios')
                  ->cascadeOnDelete();
            $table->string('etiqueta', 200);
            $table->decimal('puntaje', 5, 2);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();

            $table->index('plantilla_criterio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_opciones');
    }
};
