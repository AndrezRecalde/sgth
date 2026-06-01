<?php
namespace App\Jobs;

use App\Services\Asistencia\PeriodoVacacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarPeriodosAnualesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $anio
    ) {}

    public function handle(PeriodoVacacionService $service): void
    {
        Log::info("Generando períodos de vacaciones para el año {$this->anio}");
        $resultados = $service->generarPeriodosAnuales($this->anio);
        Log::info("Períodos generados: {$resultados->count()}");
    }
}
