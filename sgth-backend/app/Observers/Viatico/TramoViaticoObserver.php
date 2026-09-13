<?php
namespace App\Observers\Viatico;

use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\TramoViatico;
use Illuminate\Support\Facades\Log;

class TramoViaticoObserver
{
    public function created(TramoViatico $tramo): void
    {
        $this->generarAutorizacionSiAplica($tramo);
    }

    public function updated(TramoViatico $tramo): void
    {
        // Si cambió la empresa: sin autorización requerida se retira la
        // pendiente; con ella, se crea. Antes solo se retiraba, así que pasar
        // un tramo de bus a avión dejaba el vuelo sin autorizar.
        if ($tramo->wasChanged('empresa_transporte_id')) {
            $tramo->unsetRelation('empresa');

            $requiere = $tramo->empresa
                ?->catalogo
                ?->requiere_autorizacion ?? false;

            if (!$requiere) {
                AutorizacionVuelo::where(
                    'tramo_viatico_id', $tramo->id
                )->where('estado', 'pendiente')
                 ->delete();

                return;
            }

            $this->generarAutorizacionSiAplica($tramo);
        }
    }

    public function deleted(TramoViatico $tramo): void
    {
        // Eliminar autorización de vuelo asociada
        AutorizacionVuelo::where(
            'tramo_viatico_id', $tramo->id
        )->delete();
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
