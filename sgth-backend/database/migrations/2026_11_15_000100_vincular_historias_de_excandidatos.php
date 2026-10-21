<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Engancha a su servidor las historias clínicas de quienes fueron candidatos.
 *
 * El preocupacional abre la historia a nombre de la cédula, como 'candidato'
 * y sin dueño. Cuando la persona entraba a trabajar nada la enganchaba: solo
 * el triaje lo hacía, y un turno sin triaje —los de odontología, por
 * defecto— llegaba al médico sin historia y la consulta no se podía guardar.
 * «Crear historia clínica» en la recepción tampoco: devolvía la existente
 * tal cual. Ambas cosas se corrigen en el código; esto arregla las que ya
 * quedaron sueltas.
 *
 * Solo con servidores, que tienen la cédula única. Un familiar puede estar
 * registrado dos veces (una por cada padre), y elegir uno sería adivinar: a
 * esos los engancha la recepción al pedir su historia.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE historias_clinicas h
               SET servidor_id   = s.id,
                   tipo_paciente = 'servidor'
              FROM servidores s
             WHERE h.servidor_id IS NULL
               AND h.carga_familiar_id IS NULL
               AND h.deleted_at IS NULL
               AND s.cedula = h.cedula_paciente
               AND s.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM historias_clinicas otra
                    WHERE otra.servidor_id = s.id
                      AND otra.deleted_at IS NULL
               )
        SQL);
    }

    public function down(): void
    {
        // Sin vuelta atrás: no queda constancia de cuáles estaban sueltas, y
        // devolverlas a 'candidato' rompería lo que ya se atendió con ellas.
    }
};
