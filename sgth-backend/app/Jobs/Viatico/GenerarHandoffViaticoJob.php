<?php

namespace App\Jobs\Viatico;

use App\Contracts\Handoff\HandoffErpServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerarHandoffViaticoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param int $referenciaId ID del viático (si compromiso) o de la liquidación (si devengado)
     * @param string $tipo 'compromiso' o 'devengado'
     */
    public function __construct(
        public int $referenciaId,
        public string $tipo
    ) {}

    /**
     * Execute the job.
     */
    public function handle(HandoffErpServiceInterface $handoffService): void
    {
        if ($this->tipo === 'compromiso') {
            $handoffService->generarHandoffCompromisoViatico($this->referenciaId);
        } elseif ($this->tipo === 'devengado') {
            $handoffService->generarHandoffDevengadoViatico($this->referenciaId);
        } else {
            throw new \InvalidArgumentException("Tipo de handoff viático no soportado: {$this->tipo}");
        }
    }
}
