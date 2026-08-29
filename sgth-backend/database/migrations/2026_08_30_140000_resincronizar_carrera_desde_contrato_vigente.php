<?php

use App\Enums\TipoNombramiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repara los campos de carrera que quedaron sin sincronizar.
 *
 * `servidores.tipo_nombramiento` y `regimen_laboral` son campos derivados: los
 * escribe `ContratoServidorService::sincronizarRegimenServidor()` al
 * materializar el contrato. Los contratos anteriores a ese sincronizador
 * dejaron al servidor con el campo en nulo aunque tuvieran un nombramiento
 * permanente vigente — la pantalla lo mostraba bien, porque lee el contrato,
 * mientras la tabla de servidores decía otra cosa.
 *
 * Se recalcula desde el contrato VIGENTE, con la misma regla que usa el
 * sincronizador, y solo donde no coincide.
 *
 * Lo que NO se toca, a propósito:
 *
 *  - `fecha_ingreso_institucion`: el sincronizador la toma del contrato
 *    vigente, pero para alguien con varios contratos el ingreso a la
 *    institución es el PRIMERO. Rellenarla aquí exigiría decidir cuál de las
 *    dos reglas es la buena, y esa es una decisión de Talento Humano.
 *  - `fecha_ingreso_sector_publico`: no se deriva de ningún contrato de esta
 *    institución. Es antigüedad en todo el sector público y solo la sabe el
 *    expediente de la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        $servidores = DB::table('servidores as s')
            ->join('contratos_servidor as c', function ($join) {
                $join->on('c.servidor_id', '=', 's.id')
                    ->where('c.estado', 'vigente')
                    ->whereNull('c.deleted_at');
            })
            ->whereNull('s.deleted_at')
            ->whereRaw('s.tipo_nombramiento is distinct from c.tipo_nombramiento')
            ->select('s.id', 'c.tipo_nombramiento', 'c.fecha_inicio')
            ->get();

        foreach ($servidores as $fila) {
            $nombramiento = TipoNombramiento::tryFrom((string) $fila->tipo_nombramiento);

            if (! $nombramiento) {
                continue;
            }

            DB::table('servidores')->where('id', $fila->id)->update([
                'tipo_nombramiento' => $nombramiento->value,
                'regimen_laboral'   => match ($nombramiento) {
                    TipoNombramiento::SERVICIOS_PROFESIONALES => 'servicios_profesionales',
                    TipoNombramiento::CODIGO_TRABAJO          => 'codigo_trabajo',
                    default                                   => 'losep',
                },
                // Solo el nombramiento permanente tiene fecha de nombramiento.
                'fecha_nombramiento' => $nombramiento === TipoNombramiento::PERMANENTE
                    ? $fila->fecha_inicio
                    : null,
            ]);
        }

        if ($servidores->isNotEmpty()) {
            echo '  Servidores resincronizados desde su contrato vigente: '
                .$servidores->count()."\n";
        }

        /**
         * Y quien no tiene vínculo no marca.
         *
         * `servidores.puede_marcar` tiene `DEFAULT true`, así que un servidor
         * cargado sin contrato quedaba habilitado para marcar por omisión, sin
         * que nadie lo hubiera decidido. En cuanto se le registre un contrato,
         * `ContratoServidorObserver` le devuelve el valor que corresponda.
         */
        $sinVinculo = DB::table('servidores')
            ->whereNull('deleted_at')
            ->where('puede_marcar', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contratos_servidor as c')
                    ->whereColumn('c.servidor_id', 'servidores.id')
                    ->where('c.estado', 'vigente')
                    ->whereNull('c.deleted_at');
            })
            ->update(['puede_marcar' => false]);

        if ($sinVinculo > 0) {
            echo "  Servidores sin contrato vigente a los que se les quitó la marcación: {$sinVinculo}\n";
        }
    }

    public function down(): void
    {
        // No se revierte: dejaba campos derivados contradiciendo al contrato
        // del que salen. Volver a ese estado no es restaurar nada.
    }
};
