<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Desde el 2026-10-03 las marcas `persona_con_discapacidad` y
 * `posee_enfermedad_catastrofica` de una carga familiar se derivan de sus
 * registros (CondicionCargaFamiliarObserver), en vez de ponerlas un
 * interruptor del formulario.
 *
 * Esta migración enciende la marca a quien tiene registros y la tenía apagada.
 * NO apaga a nadie: un familiar marcado sin detalle es una declaración de
 * Talento Humano que falta completar, y la pantalla la enseña como «sin
 * detalle» para que se complete. Borrarla sería perder el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE cargas_familiares c
               SET persona_con_discapacidad = true
             WHERE c.persona_con_discapacidad = false
               AND EXISTS (SELECT 1 FROM discapacidades_carga_familiar d
                            WHERE d.carga_familiar_id = c.id AND d.deleted_at IS NULL)
        SQL);

        DB::statement(<<<'SQL'
            UPDATE cargas_familiares c
               SET posee_enfermedad_catastrofica = true
             WHERE c.posee_enfermedad_catastrofica = false
               AND EXISTS (SELECT 1 FROM enfermedades_catastroficas_carga_familiar e
                            WHERE e.carga_familiar_id = c.id AND e.deleted_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        // Sin vuelta atrás: encender una marca que tenía registros corrige un
        // dato, no cambia el esquema.
    }
};
