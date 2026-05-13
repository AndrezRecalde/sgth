<?php
namespace App\Jobs\Dispensario;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Dispensario\InventarioMedicina;
use Illuminate\Support\Facades\Log;

class VerificarAlertasInventarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // 1. Alerta de Stock Mínimo
        $medicinasSinStock = InventarioMedicina::whereColumn('stock_actual', '<=', 'stock_minimo')
            ->where('estado', true)
            ->get();

        foreach ($medicinasSinStock as $medicina) {
            Log::warning("ALERTA DE STOCK DISPENSARIO: La medicina {$medicina->nombre} tiene un stock actual de {$medicina->stock_actual}, mínimo requerido: {$medicina->stock_minimo}");
            // En el sistema real se emite un Notification::send() al admin-dispensario
        }

        // 2. Alerta de Caducidad (60 días)
        $fechaLimite = now()->addDays(60)->toDateString();
        $medicinasPorCaducar = InventarioMedicina::where('fecha_caducidad', '<=', $fechaLimite)
            ->where('fecha_caducidad', '>=', now()->toDateString())
            ->where('estado', true)
            ->get();

        foreach ($medicinasPorCaducar as $medicina) {
            Log::warning("ALERTA DE CADUCIDAD DISPENSARIO: La medicina {$medicina->nombre} caducará el {$medicina->fecha_caducidad}. Faltan menos de 60 días.");
            // En el sistema real se emite un Notification::send() al admin-dispensario
        }
    }
}
