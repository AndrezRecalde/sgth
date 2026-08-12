<?php

namespace App\Console\Commands;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use Illuminate\Console\Command;

/**
 * Cierra las subrogaciones y encargos que ya cumplieron su plazo.
 *
 * A diferencia de sgth:contratos:detectar-vencidos, esto no genera nada para
 * que Talento Humano revise: la fecha de fin ya venía autorizada en la Acción
 * de Personal, así que llegar a ella no requiere una decisión nueva. Solo
 * pone el estado al día.
 */
class CaducarSubrogacionesCommand extends Command
{
    protected $signature = 'sgth:subrogaciones:caducar {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Marca como finalizadas las subrogaciones y encargos cuyo plazo ya venció.';

    public function handle(SubrogacionServiceInterface $servicio): int
    {
        $resultado = $servicio->caducarVencidas($this->option('fecha'));

        $this->info(
            $resultado['caducadas'].' subrogación(es)/encargo(s) finalizados '
            ."por vencimiento de plazo al {$resultado['fecha']}."
        );

        return self::SUCCESS;
    }
}
