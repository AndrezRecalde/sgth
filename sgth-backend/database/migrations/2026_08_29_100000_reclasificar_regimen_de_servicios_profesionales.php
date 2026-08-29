<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reclasifica al régimen propio a quienes tienen un contrato vigente de
 * servicios profesionales.
 *
 * Hasta el 2026-08-29 `RegimenLaboral` solo tenía `losep` y `codigo_trabajo`, y
 * los servicios profesionales se guardaban como Código del Trabajo por ser «lo
 * que no es LOSEP». El síntoma que lo destapó: un profesional contratado sobre
 * un puesto LOSEP aparecía con régimen Código de Trabajo sin que nadie lo
 * hubiera elegido en ningún formulario.
 *
 * Se decide por el `tipo_nombramiento` del CONTRATO VIGENTE, que es el dato que
 * el sistema ya usaba para derivar el régimen. No se toca a nadie más: un
 * obrero real debe seguir en Código del Trabajo.
 *
 * La columna `servidores.regimen_laboral` es varchar sin CHECK, así que no hace
 * falta alterar el esquema. La de `puestos` sí lo tiene, y se deja como está:
 * un puesto es una plaza de la estructura, siempre LOSEP o Código del Trabajo;
 * el contrato civil se firma sobre un puesto, no crea uno de su régimen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $afectados = DB::table('servidores')
            ->whereIn('id', function ($q) {
                $q->select('servidor_id')
                    ->from('contratos_servidor')
                    ->where('tipo_nombramiento', 'servicios_profesionales')
                    ->where('estado', 'vigente');
            })
            ->update(['regimen_laboral' => 'servicios_profesionales']);

        if ($afectados > 0) {
            echo "  Reclasificados {$afectados} servidor(es) a régimen de servicios profesionales.\n";
        }
    }

    public function down(): void
    {
        // Vuelven al cajón anterior, que es donde estaban.
        DB::table('servidores')
            ->where('regimen_laboral', 'servicios_profesionales')
            ->update(['regimen_laboral' => 'codigo_trabajo']);
    }
};
