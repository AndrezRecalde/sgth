<?php

namespace App\Jobs\Nomina;

use App\Models\Nomina\RolPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarRolPagoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $rolPagoId)
    {
        $this->onQueue('correos');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $rol = RolPago::with('servidor')->find($this->rolPagoId);
        
        if (!$rol || !$rol->servidor || !$rol->servidor->usuario?->email) {
            return;
        }

        // Aquí se enviaría el correo real con Mail::to(...)
        Log::info("Simulando envío de rol de pago a: {$rol->servidor->usuario?->email}");

        $rol->enviado_por_correo = true;
        $rol->enviado_en = now();
        $rol->save();
    }
}
