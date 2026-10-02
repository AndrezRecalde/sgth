<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'duracion_horas' de capacitaciones_sso pasa de integer a decimal(5,2).
 *
 * La columna nació como `$table->integer('duracion_horas')`, pero las dos
 * requests que la validan dicen `['required', 'numeric', 'min:0.5']`. Ese
 * `min:0.5` no está ahí por casualidad: una capacitación de media hora, de
 * una hora y media o de dos y cuarto es lo normal en seguridad y salud.
 *
 * Con la columna en integer, registrar 2.5 horas no daba un 422 sino un 500:
 *
 *   SQLSTATE[22P02]: invalid input syntax for type integer: "2.5"
 *
 * Es el mismo patrón que `dias_reposo_medico` declarando `nullable` sobre una
 * columna `NOT NULL`: la validación promete lo que el esquema no acepta, y el
 * usuario recibe «No se pudo registrar la capacitación» sin saber por qué.
 *
 * Se ensancha la columna y no se estrecha la validación porque las horas
 * fraccionarias son el caso real, y porque esta cifra se suma en
 * `horas_capacitacion_total`, uno de los índices proactivos del módulo:
 * redondear cada capacitación a horas enteras desvía el total del período.
 *
 * decimal(5,2) deja hasta 999,99 horas en una sola capacitación, de sobra, y
 * dos decimales cubren los cuartos de hora.
 *
 * SQL directo y no ->change(): doctrine/dbal no está instalado y es una sola
 * columna, igual que en `descripcion_de_riesgo_laboral_a_texto`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE capacitaciones_sso ALTER COLUMN duracion_horas TYPE decimal(5,2)');
    }

    public function down(): void
    {
        // Volver a integer no cabe sin perder datos: las fracciones se
        // redondean antes de estrechar la columna. Una capacitación de 2,5
        // horas vuelve como 3, y `horas_capacitacion_total` del período se
        // mueve con ella.
        DB::statement('UPDATE capacitaciones_sso SET duracion_horas = ROUND(duracion_horas)');

        DB::statement('ALTER TABLE capacitaciones_sso ALTER COLUMN duracion_horas TYPE integer');
    }
};
