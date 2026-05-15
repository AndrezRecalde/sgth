<?php

namespace App\Observers\Viatico;

use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\TransporteViatico;
use App\Mail\Viatico\AutorizacionVueloPendienteMail;
use Illuminate\Support\Facades\Mail;

class TransporteViaticoObserver
{
    /**
     * Handle the TransporteViatico "created" event.
     */
    public function created(TransporteViatico $transporte): void
    {
        if ($transporte->tipo === 'avion') {
            $autorizacion = AutorizacionVuelo::create([
                'transporte_viatico_id' => $transporte->id,
                'viatico_id'            => $transporte->viatico_id,
                'estado'                => 'pendiente',
                'justificacion'         => 'Requerido sistema automático por viaje en avión.',
            ]);

            $maximasAutoridades = User::role('maxima-autoridad')->get();
            if ($maximasAutoridades->isNotEmpty()) {
                Mail::to($maximasAutoridades)->send(new AutorizacionVueloPendienteMail($autorizacion));
            }
        }
    }
}
