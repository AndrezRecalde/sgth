<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_empleos_anteriores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->string('centro_trabajo', 200);
            $table->text('actividades_desempenadas')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_empleos_anteriores');
    }
};
