<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puesto_epp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('puestos')->cascadeOnDelete();
            $table->foreignId('equipo_proteccion_id')->constrained('equipos_proteccion')->cascadeOnDelete();
            $table->unsignedSmallInteger('cantidad_requerida')->default(1);
            $table->unsignedSmallInteger('frecuencia_reposicion_meses')->nullable();
            $table->timestamps();

            $table->unique(['puesto_id', 'equipo_proteccion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puesto_epp');
    }
};
