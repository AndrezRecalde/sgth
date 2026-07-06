<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados_medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_clinica_id')
                  ->constrained('historias_clinicas')
                  ->cascadeOnDelete();
            $table->foreignId('consulta_medica_id')
                  ->nullable()
                  ->constrained('consultas_medicas')
                  ->nullOnDelete();
            $table->foreignId('subido_por')
                  ->constrained('users');
            $table->enum('tipo', [
                'laboratorio', 'imagen',
                'ecografia', 'rayos_x',
                'electrocardiograma', 'otro',
            ]);
            $table->string('descripcion');
            $table->string('archivo');
            $table->date('fecha_resultado');
            $table->timestamps();
            $table->softDeletes();

            $table->index('historia_clinica_id');
            $table->index('consulta_medica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados_medicos');
    }
};
