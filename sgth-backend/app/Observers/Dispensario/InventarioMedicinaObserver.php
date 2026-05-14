<?php

namespace App\Observers\Dispensario;

use App\Models\Dispensario\InventarioMedicina;
use Illuminate\Support\Facades\Cache;

class InventarioMedicinaObserver
{
    public function updated(InventarioMedicina $inventario): void
    {
        if ($inventario->wasChanged('stock_actual')) {
            Cache::forget('sgth:dashboard:kpis');
        }
    }
}
