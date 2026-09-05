<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El CHECK de agendas_medicas seguía exigiendo `beneficiario_id`, la columna
 * que quedó abandonada cuando el dispensario pasó a apuntar a cargas
 * familiares. Un turno para un familiar llega con servidor_id y beneficiario_id
 * en null, así que no cumplía ninguna de las dos ramas y la base lo rechazaba:
 * atender a un familiar era imposible, no por regla de negocio sino por un
 * constraint que se quedó mirando a la columna anterior.
 *
 * Lo mismo se corrigió en historias_clinicas en su día (ver
 * 2026_07_14_163259); a agendas_medicas no le llegó el turno.
 *
 * La columna `beneficiario_id` se deja donde está —no la usa nadie y no hay
 * una sola fila que la llene— porque quitarla es otra conversación.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE agendas_medicas
             DROP CONSTRAINT IF EXISTS chk_paciente_exclusivo'
        );

        // Exactamente un paciente, que es lo mismo que ya valida
        // StoreAgendaMedicaRequest.
        DB::statement("
            ALTER TABLE agendas_medicas
            ADD CONSTRAINT chk_paciente_exclusivo CHECK (
                (servidor_id IS NOT NULL AND carga_familiar_id IS NULL)
                OR
                (servidor_id IS NULL AND carga_familiar_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE agendas_medicas
             DROP CONSTRAINT IF EXISTS chk_paciente_exclusivo'
        );

        DB::statement("
            ALTER TABLE agendas_medicas
            ADD CONSTRAINT chk_paciente_exclusivo CHECK (
                (servidor_id IS NOT NULL AND beneficiario_id IS NULL)
                OR
                (servidor_id IS NULL AND beneficiario_id IS NOT NULL)
            )
        ");
    }
};
