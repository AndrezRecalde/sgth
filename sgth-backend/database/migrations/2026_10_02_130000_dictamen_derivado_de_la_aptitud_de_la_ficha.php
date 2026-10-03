<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El dictamen de la solicitud pasa a ser la aptitud de la ficha FEMO.
 *
 * Hasta ahora el médico elegía la aptitud en la sección L (cuatro valores) y
 * después, en otro modal, un dictamen de tres. Podían contradecirse: Talento
 * Humano incorporaba con el dictamen y el certificado imprimía la aptitud.
 * Decidido con el usuario el 2026-10-02: el dictamen se deriva de la aptitud,
 * así que su columna admite también «en_observacion» (que no bloquea la
 * incorporación ni lleva fecha de reevaluación).
 *
 * La aptitud de la ficha admite nulo: la ficha se guarda como borrador mientras
 * se llena, y la aptitud se exige al emitir el dictamen, no antes. Antes nacía
 * marcada en «apto» y una ficha guardada sin pensarlo quedaba apta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE solicitudes_certificacion_medica DROP CONSTRAINT IF EXISTS solicitudes_certificacion_medica_dictamen_check');
        DB::statement(
            'ALTER TABLE solicitudes_certificacion_medica ADD CONSTRAINT solicitudes_certificacion_medica_dictamen_check '
            ."CHECK (dictamen IN ('apto', 'apto_con_restricciones', 'en_observacion', 'no_apto'))"
        );

        DB::statement('ALTER TABLE fichas_salud_ocupacional ALTER COLUMN aptitud DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE fichas_salud_ocupacional SET aptitud = 'apto' WHERE aptitud IS NULL");
        DB::statement('ALTER TABLE fichas_salud_ocupacional ALTER COLUMN aptitud SET NOT NULL');

        DB::statement("UPDATE solicitudes_certificacion_medica SET dictamen = 'apto' WHERE dictamen = 'en_observacion'");
        DB::statement('ALTER TABLE solicitudes_certificacion_medica DROP CONSTRAINT IF EXISTS solicitudes_certificacion_medica_dictamen_check');
        DB::statement(
            'ALTER TABLE solicitudes_certificacion_medica ADD CONSTRAINT solicitudes_certificacion_medica_dictamen_check '
            ."CHECK (dictamen IN ('apto', 'apto_con_restricciones', 'no_apto'))"
        );
    }
};
