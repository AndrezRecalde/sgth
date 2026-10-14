<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los técnicos de la mesa de ayuda por subunidad de TIC (D6; fase 3.5).
 *
 * `areas_dtic` repetía a mano lo que la estructura orgánica ya tiene: las
 * subunidades de TIC (Soporte Técnico, Infraestructura y Redes, Desarrollo de
 * Software, Seguridad de la Información). Se retira, y cada técnico apunta a
 * su subunidad. Las dos tablas están vacías: no hay nada que migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tecnicos_dtic', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_dtic_id');
            $table->foreignId('unidad_tic_id')->nullable()->constrained('unidades_administrativas');
        });

        Schema::dropIfExists('areas_dtic');
    }

    public function down(): void
    {
        Schema::create('areas_dtic', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tecnicos_dtic', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_tic_id');
            $table->foreignId('area_dtic_id')->nullable()->constrained('areas_dtic')->restrictOnDelete();
        });
    }
};
