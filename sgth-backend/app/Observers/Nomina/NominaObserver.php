<?php

namespace App\Observers\Nomina;

use App\Enums\EstadoNomina;
use App\Models\Nomina\Nomina;
use Illuminate\Support\Facades\Cache;

class NominaObserver
{
    public function updated(Nomina $nomina): void
    {
        if ($nomina->wasChanged('estado') && $nomina->estado === EstadoNomina::CERRADA) {
            Cache::forget('sgth:dashboard:kpis');
            Cache::tags(['reporteria'])->flush();
        }
    }
}
