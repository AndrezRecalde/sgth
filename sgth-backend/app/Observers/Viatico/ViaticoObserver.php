<?php

namespace App\Observers\Viatico;

use App\Models\Viatico\Viatico;
use Illuminate\Support\Facades\Cache;
use App\Services\Viatico\CodigoViaticoService;

class ViaticoObserver
{
    public function creating(Viatico $viatico): void
    {
        if (empty($viatico->codigo_viatico)) {
            $service = app(CodigoViaticoService::class);
            $viatico->codigo_viatico = $service->generarCodigoViatico($viatico);
        }
    }

    public function updated(Viatico $viatico): void
    {
        if ($viatico->wasChanged('estado')) {
            Cache::forget('sgth:dashboard:kpis');
        }
    }
}
