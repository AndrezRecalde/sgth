<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_antecedentes_reproductivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->unique()
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->date('fecha_ultima_menstruacion')->nullable();
            $table->smallInteger('gestas')->nullable();
            $table->smallInteger('partos')->nullable();
            $table->smallInteger('cesareas')->nullable();
            $table->smallInteger('abortos')->nullable();
            $table->enum('usa_metodo_planificacion', ['si', 'no', 'no_responde'])->nullable();
            $table->string('metodo_planificacion_cual', 200)->nullable();
            $table->string('examenes_realizados', 300)->nullable();
            $table->smallInteger('examenes_tiempo_anios')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_antecedentes_reproductivos');
    }
};
