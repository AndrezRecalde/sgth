<?php

namespace App\Observers\Dispensario;

use App\Models\Dispensario\InventarioMedicina;
use Illuminate\Support\Facades\Cache;

class InventarioMedicinaObserver
{
    /**
     * Una medicina que nace con existencias necesita un lote que las sostenga.
     *
     * Por la puerta normal esto no ocurre: `ingresarMedicina` crea el catálogo
     * en cero y el stock entra por adquisiciones, cada una con su lote. Pero
     * crear el modelo a pelo —una siembra, un arreglo puntual— dejaba stock sin
     * lote, y a la primera salida el reparto no cuadraba. Se le abre un lote
     * sin identificar, que es lo mismo que hizo la migración inicial con las
     * existencias que ya había: decir lo que se sabe y nada más.
     */
    public function created(InventarioMedicina $inventario): void
    {
        if ($inventario->stock_actual > 0) {
            $inventario->lotes()->create([
                'codigo_lote'        => null,
                // Sin fecha: quien crea la ficha con existencias directamente
                // no está declarando de qué lote son ni cuándo caducan.
                'fecha_caducidad'    => null,
                'cantidad_ingresada' => $inventario->stock_actual,
                'stock_actual'       => $inventario->stock_actual,
            ]);
        }
    }

    public function updated(InventarioMedicina $inventario): void
    {
        if ($inventario->wasChanged('stock_actual')) {
            Cache::forget('sgth:dashboard:kpis');
        }
    }
}
