<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_consumo_sustancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->enum('sustancia', ['tabaco', 'alcohol', 'otra']);
            $table->string('sustancia_otra_detalle', 100)->nullable();
            $table->smallInteger('tiempo_consumo_meses')->nullable();
            $table->boolean('ex_consumidor')->default(false);
            $table->smallInteger('tiempo_abstinencia_meses')->nullable();
            $table->boolean('no_consume')->default(false);
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_consumo_sustancias');
    }
};
