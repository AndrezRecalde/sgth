<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_ficha_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                ->constrained('fichas_salud_ocupacional')
                ->cascadeOnDelete();
            $table->foreignId('puesto_actividad_id')
                ->nullable()
                ->constrained('puesto_actividades')
                ->nullOnDelete();
            $table->string('actividad', 200);
            $table->text('medida_preventiva')->nullable();
            $table->smallInteger('orden')->default(1);
            $table->timestamps();

            $table->index('ficha_id');
        });

        Schema::table('femo_factores_riesgo', function (Blueprint $table) {
            $table->foreignId('ficha_actividad_id')
                ->nullable()
                ->after('ficha_id')
                ->constrained('femo_ficha_actividades')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('femo_factores_riesgo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ficha_actividad_id');
        });

        Schema::dropIfExists('femo_ficha_actividades');
    }
};
