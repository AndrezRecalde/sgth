<?php

namespace App\Console\Commands;

use App\Services\Helpdesk\Sla\SeguimientoTicketService;
use Illuminate\Console\Command;

/**
 * El seguimiento del SLA de la mesa de ayuda (fase 3.2): la alerta de por
 * vencer, el escalamiento de los vencidos y el cierre automático de los
 * resueltos sin respuesta.
 */
class SeguimientoHelpdeskCommand extends Command
{
    protected $signature = 'sgth:helpdesk:seguimiento';

    protected $description = 'Alerta los tickets por vencer, escala los vencidos y cierra los resueltos sin respuesta del usuario.';

    public function handle(SeguimientoTicketService $seguimiento): int
    {
        $r = $seguimiento->correr();

        $this->info("{$r['por_vencer']} alerta(s) de por vencer, {$r['vencidos']} ticket(s) vencido(s) y {$r['cerrados']} cerrado(s) solo(s).");

        return self::SUCCESS;
    }
}
