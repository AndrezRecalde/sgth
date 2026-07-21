<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de actividades del Programa de prevención integral de drogas
        // (Instructivo MDT-MSP-2019-038), agrupadas por una de las 6 fases oficiales.
        // Se siembra con las actividades textuales del instructivo (seeder) y admite
        // que la institución agregue actividades propias adicionales.
        Schema::create('programa_drogas_actividades', function (Blueprint $table) {
            $table->id();
            $table->string('fase', 40);
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('fase');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_drogas_actividades');
    }
};
