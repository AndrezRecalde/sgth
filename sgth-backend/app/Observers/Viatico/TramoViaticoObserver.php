<?php
namespace App\Observers\Viatico;

use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\TramoViatico;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TramoViaticoObserver
{
    public function created(TramoViatico $tramo): void
    {
        $this->sincronizarFechasViatico($tramo);
        $this->generarAutorizacionSiAplica($tramo);
    }

    public function updated(TramoViatico $tramo): void
    {
        $this->sincronizarFechasViatico($tramo);
    }

    public function deleted(TramoViatico $tramo): void
    {
        $this->sincronizarFechasViatico($tramo);
    }

    private function sincronizarFechasViatico(
        TramoViatico $tramo
    ): void {
        $viatico = $tramo->viatico;
        if (!$viatico) return;

        $tramos = $viatico->tramos()->get();

        if ($tramos->isEmpty()) {
            $viatico->update([
                'datetime_salida'  => null,
                'datetime_llegada' => null,
                'total_dias'       => 0,
            ]);
            return;
        }

        $salida  = $tramos->min('datetime_salida');
        $llegada = $tramos->max('datetime_llegada');

        $totalDias = 0;
        if ($salida && $llegada) {
            $totalDias = round(
                Carbon::parse($salida)
                    ->diffInHours(Carbon::parse($llegada)) / 24,
                2
            );
        }

        $viatico->update([
            'datetime_salida'  => $salida,
            'datetime_llegada' => $llegada,
            'total_dias'       => $totalDias,
        ]);
    }

    private function generarAutorizacionSiAplica(
        TramoViatico $tramo
    ): void {
        $requiere = $tramo->empresa
            ?->catalogo
            ?->requiere_autorizacion ?? false;

        if (!$requiere) return;

        AutorizacionVuelo::firstOrCreate(
            ['tramo_viatico_id' => $tramo->id],
            [
                'viatico_id' => $tramo->viatico_id,
                'estado'     => 'pendiente',
            ]
        );

        Log::info(
            "AutorizacionVuelo creada para tramo {$tramo->id} " .
            "del viático {$tramo->viatico_id}"
        );
    }
}
