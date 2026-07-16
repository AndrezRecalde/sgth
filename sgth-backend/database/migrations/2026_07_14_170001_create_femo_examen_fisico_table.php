<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_examen_fisico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->enum('region', [
                'piel', 'ojos', 'oido', 'orofaringe', 'nariz', 'cuello',
                'torax_1', 'torax_2', 'abdomen', 'columna',
                'pelvis', 'extremidades', 'neurologico',
            ]);
            $table->string('item', 100);
            $table->boolean('normal')->default(true);
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->index(['ficha_id', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_examen_fisico');
    }
};
