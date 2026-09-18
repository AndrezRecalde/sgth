<?php
namespace App\Observers\Viatico;

use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use Illuminate\Support\Facades\Log;

class TramoViaticoObserver
{
    /**
     * Sin tipo de transporte, el tramo toma el de su empresa.
     *
     * Antes de 2026-09-18 el tramo solo guardaba la empresa. Quien todavía
     * manda solo la empresa —y los tramos que se crean fuera del formulario—
     * sigue funcionando.
     */
    public function saving(TramoViatico $tramo): void
    {
        if ($tramo->catalogo_transporte_id === null && $tramo->empresa_transporte_id !== null) {
            $tramo->catalogo_transporte_id = EmpresaTransporte::whereKey($tramo->empresa_transporte_id)
                ->value('catalogo_transporte_id');
        }
    }

    public function created(TramoViatico $tramo): void
    {
        $this->generarAutorizacionSiAplica($tramo);
    }

    public function updated(TramoViatico $tramo): void
    {
        // Si cambió el tipo de transporte: sin autorización requerida se
        // retira la pendiente; con ella, se crea. Antes solo se retiraba, así
        // que pasar un tramo de bus a avión dejaba el vuelo sin autorizar.
        if ($tramo->wasChanged('catalogo_transporte_id')) {
            $tramo->unsetRelation('catalogo');

            if (! $this->requiereAutorizacion($tramo)) {
                AutorizacionVuelo::where('tramo_viatico_id', $tramo->id)
                    ->where('estado', 'pendiente')
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

    /** Lo decide el tipo del tramo, no la empresa: puede no tenerla. */
    private function requiereAutorizacion(TramoViatico $tramo): bool
    {
        return (bool) ($tramo->catalogo?->requiere_autorizacion ?? false);
    }

    private function generarAutorizacionSiAplica(
        TramoViatico $tramo
    ): void {
        if (! $this->requiereAutorizacion($tramo)) return;

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
