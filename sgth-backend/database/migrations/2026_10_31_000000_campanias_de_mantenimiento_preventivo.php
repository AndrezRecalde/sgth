<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plan de mantenimiento preventivo por campaña (fase 5.1), como la pestaña
 * «Planificador de proyectos» del Excel de TIC: por ubicación, con un técnico,
 * los equipos, el período y el avance. Cada equipo hecho es un mantenimiento
 * preventivo de verdad en su ficha (`mantenimientos_bien`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanias_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            // Qué se hace en cada equipo: va como descripción del mantenimiento.
            $table->string('descripcion', 500);
            $table->foreignId('ubicacion_id')->constrained('ubicaciones');
            $table->foreignId('unidad_administrativa_id')->nullable()->constrained('unidades_administrativas');
            $table->foreignId('tecnico_id')->constrained('users');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrada')->default(false);
            $table->foreignId('cerrada_por')->nullable()->constrained('users');
            $table->timestamp('cerrada_en')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['cerrada', 'fecha_inicio']);
        });

        Schema::create('campania_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_id')->constrained('campanias_mantenimiento')->cascadeOnDelete();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos');
            // pendiente, hecho, omitido
            $table->string('estado', 15)->default('pendiente');
            $table->foreignId('mantenimiento_bien_id')->nullable()->constrained('mantenimientos_bien')->nullOnDelete();
            $table->string('motivo_omision', 300)->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['campania_id', 'bien_informatico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campania_equipos');
        Schema::dropIfExists('campanias_mantenimiento');
    }
};
