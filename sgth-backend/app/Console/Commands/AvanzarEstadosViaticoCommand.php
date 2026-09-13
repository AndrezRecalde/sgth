<?php

namespace App\Console\Commands;

use App\Services\Viatico\ViaticoEstadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pone al día el estado de los viáticos según las fechas del viaje.
 *
 * Llegada la salida, lo aprobado pasa a «en comisión»; llegado el regreso, a
 * «pendiente de liquidación». Sin esto dependía de que Financiero pulsara el
 * botón, y el plazo de 5 días hábiles para liquidar no empezaba nunca.
 *
 * Las reglas están en `ViaticoEstadoService::avanzarPorFechas()`.
 */
class AvanzarEstadosViaticoCommand extends Command
{
    protected $signature = 'sgth:viaticos:avanzar-estados {--ahora= : Momento de corte (Y-m-d H:i), por defecto ahora}';

    protected $description = 'Pasa los viáticos a en comisión y a pendiente de liquidación según sus fechas.';

    public function handle(ViaticoEstadoService $estados): int
    {
        $ahora = $this->option('ahora') ? Carbon::parse($this->option('ahora')) : Carbon::now();

        $resultado = $estados->avanzarPorFechas($ahora);

        $this->info(
            "{$resultado['en_comision']} viático(s) pasaron a en comisión y "
            . "{$resultado['pendiente_liquidacion']} a pendiente de liquidación, "
            . "con corte al {$ahora->format('Y-m-d H:i')}."
        );

        return self::SUCCESS;
    }
}
