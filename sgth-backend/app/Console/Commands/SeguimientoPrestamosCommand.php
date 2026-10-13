<?php

namespace App\Console\Commands;

use App\Services\InventarioTi\Prestamos\SeguimientoPrestamoService;
use Illuminate\Console\Command;

/**
 * El seguimiento del préstamo de equipos (fase 2.4): cierra las reservas no
 * retiradas, recuerda la devolución y avisa de los vencidos. Nada de esto
 * pide una decisión: lo marcan el reloj y las reglas de TIC.
 */
class SeguimientoPrestamosCommand extends Command
{
    protected $signature = 'sgth:prestamos:seguimiento';

    protected $description = 'Cierra las reservas de préstamo no retiradas y envía los recordatorios y avisos de vencido.';

    public function handle(SeguimientoPrestamoService $seguimiento): int
    {
        $r = $seguimiento->correr();

        $this->info("{$r['no_retirados']} reserva(s) no retirada(s), {$r['recordatorios']} recordatorio(s) y {$r['vencidos']} aviso(s) de vencido.");

        return self::SUCCESS;
    }
}
