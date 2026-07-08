<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_examenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->string('nombre_examen', 200);
            $table->text('resultado')->nullable();
            $table->date('fecha_examen')->nullable();
            $table->enum('tipo', ['laboratorio', 'imagen', 'otro'])
                  ->default('laboratorio');
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_examenes');
    }
};
