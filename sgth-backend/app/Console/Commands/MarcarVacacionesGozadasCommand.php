<?php

namespace App\Console\Commands;

use App\Contracts\Asistencia\VacacionServiceInterface;
use Illuminate\Console\Command;

/**
 * Pasa a «gozada» las vacaciones aprobadas cuya fecha de fin ya pasó.
 *
 * El estado estaba en el enum desde la primera migración y nada lo asignaba:
 * el filtro «Gozada» salía siempre vacío, y una vacación de hace meses seguía
 * «aprobada», como si todavía estuviera por ocurrir.
 *
 * Como en sgth:subrogaciones:caducar, no hay nada que revisar: las fechas ya
 * venían aprobadas, y llegar al final de ellas solo pone el estado al día.
 */
class MarcarVacacionesGozadasCommand extends Command
{
    protected $signature = 'sgth:vacaciones:marcar-gozadas {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Marca como gozadas las vacaciones aprobadas que ya terminaron.';

    public function handle(VacacionServiceInterface $servicio): int
    {
        $resultado = $servicio->marcarGozadas($this->option('fecha'));

        $this->info(
            $resultado['marcadas'].' solicitud(es) de vacaciones pasaron a gozada '
            ."con corte al {$resultado['fecha']}."
        );

        return self::SUCCESS;
    }
}
