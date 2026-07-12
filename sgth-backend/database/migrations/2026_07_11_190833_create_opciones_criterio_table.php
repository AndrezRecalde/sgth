<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones_criterio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterio_id')
                  ->constrained('criterios_evaluacion')
                  ->cascadeOnDelete();
            $table->string('etiqueta', 200);
            $table->decimal('puntaje', 5, 2);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();

            $table->index('criterio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones_criterio');
    }
};
