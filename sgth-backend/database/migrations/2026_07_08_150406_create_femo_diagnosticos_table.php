<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->foreignId('diagnostico_cie10_id')
                  ->constrained('diagnosticos_cie10');
            $table->enum('tipo', ['presuntivo', 'definitivo']);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_diagnosticos');
    }
};
