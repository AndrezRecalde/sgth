<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'autorizado_por' pasa a ser nullable porque hay acciones que nacen sin que
 * nadie las haya autorizado todavía: el vencimiento de un contrato de
 * Servicios Profesionales genera su cesación en borrador desde una tarea
 * programada, sin usuario en sesión. Quien la autoriza de verdad queda
 * registrado al aprobarla, y ese acto ya se audita en el activity log.
 *
 * Se usa SQL directo en vez de ->change(): doctrine/dbal no está instalado y
 * el cambio es una sola columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE movimientos_personal ALTER COLUMN autorizado_por DROP NOT NULL');
    }

    public function down(): void
    {
        // Las filas generadas automáticamente no tienen a quién atribuirse, así
        // que se descartan antes de restaurar la restricción.
        DB::table('movimientos_personal')->whereNull('autorizado_por')->delete();

        DB::statement('ALTER TABLE movimientos_personal ALTER COLUMN autorizado_por SET NOT NULL');
    }
};
