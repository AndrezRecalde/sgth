<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_academico_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->enum('tipo_estudio', ['estudio', 'capacitacion']);
            $table->enum('nivel_estudio', [
                'primaria', 'secundaria', 'tercer_nivel', 'cuarto_nivel'
            ])->nullable();

            $table->enum('nacionalidad_estudio', ['nacional', 'internacional']);
            $table->string('institucion', 200);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('titulo_capacitacion', 300);
            $table->string('codigo_senescyt', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_academico_servidor');
    }
};