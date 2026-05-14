<?php

namespace App\Jobs\Nomina;

use App\Contracts\Nomina\NominaServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarCierreNominaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $nominaId,
        public int $userId
    ) {
        $this->onQueue('nomina');
    }

    /**
     * Execute the job.
     */
    public function handle(NominaServiceInterface $nominaService): void
    {
        $nominaService->cerrarNomina($this->nominaId, $this->userId);
    }
}
