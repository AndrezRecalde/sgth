<?php

namespace App\Console\Commands;

use App\Contracts\Dispensario\AgendaServiceInterface;
use Illuminate\Console\Command;

/**
 * Cierra los turnos del Dispensario que quedaron esperando de días anteriores.
 *
 * Pasan a «no se presentó» sin usuario que lo marque. Los que quedaron «en
 * consulta» no se tocan: puede haber una consulta en borrador.
 */
class CerrarTurnosVencidosCommand extends Command
{
    protected $signature = 'sgth:dispensario:cerrar-turnos-vencidos {--fecha= : Se cierran los anteriores a esta fecha (Y-m-d), por defecto hoy}';

    protected $description = 'Pasa a «no se presentó» los turnos de días anteriores que siguen en espera o listos para pasar.';

    public function handle(AgendaServiceInterface $agenda): int
    {
        $resultado = $agenda->cerrarVencidos($this->option('fecha'));

        $this->info(
            "{$resultado['cerrados']} turno(s) anteriores al {$resultado['fecha']} "
            .'cerrados como «no se presentó».'
        );

        return self::SUCCESS;
    }
}
