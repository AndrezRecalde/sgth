<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las opciones marcadas de un criterio tipo checklist (2026-10-05). La tabla
 * de calificaciones guarda una fila por criterio con un solo `opcion_id`: el
 * frontend mandaba una fila por opción marcada, `updateOrCreate` se quedaba
 * con la última y el total sumaba todas. Al volver a abrir la calificación,
 * solo aparecía marcada una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seleccion_calificacion_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calificacion_id')
                ->constrained('seleccion_calificaciones')
                ->cascadeOnDelete();
            $table->foreignId('opcion_id')
                ->constrained('seleccion_opciones')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['calificacion_id', 'opcion_id']);
        });

        // Lo que se alcanzó a guardar —la última opción marcada— pasa a la
        // tabla nueva; el resto de lo marcado ya se había perdido.
        DB::statement("
            INSERT INTO seleccion_calificacion_opciones (calificacion_id, opcion_id, created_at, updated_at)
            SELECT cal.id, cal.opcion_id, NOW(), NOW()
            FROM seleccion_calificaciones cal
            JOIN seleccion_criterios cri ON cri.id = cal.criterio_id
            WHERE cri.tipo_input = 'checklist' AND cal.opcion_id IS NOT NULL
        ");

        DB::statement("
            UPDATE seleccion_calificaciones cal SET opcion_id = NULL
            FROM seleccion_criterios cri
            WHERE cri.id = cal.criterio_id AND cri.tipo_input = 'checklist'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('seleccion_calificacion_opciones');
    }
};
