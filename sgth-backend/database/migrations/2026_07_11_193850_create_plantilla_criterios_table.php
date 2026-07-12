<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_criterios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')
                  ->constrained('plantillas_evaluacion')
                  ->cascadeOnDelete();
            $table->enum('seccion', ['meritos', 'oposicion']);
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->decimal('puntaje_maximo', 5, 2);
            $table->enum('tipo_input', ['radio', 'numero', 'checklist']);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['plantilla_id', 'seccion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_criterios');
    }
};
