<?php
namespace App\Jobs\Helpdesk;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Contracts\Helpdesk\SlaServiceInterface;

class EnviarAlertaSlaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlaServiceInterface $slaService): void
    {
        // Se delega al SlaService toda la lógica de validación, escalación y envío de alertas
        $slaService->verificarTicketsAbiertos();
    }
}
