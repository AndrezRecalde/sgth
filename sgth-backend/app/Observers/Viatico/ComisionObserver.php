<?php

namespace App\Observers\Viatico;

use App\Models\Viatico\Comision;
use App\Services\Viatico\CodigoViaticoService;

class ComisionObserver
{
    public function creating(Comision $comision): void
    {
        if (empty($comision->codigo_comision)) {
            $service = app(CodigoViaticoService::class);
            $comision->codigo_comision = $service->generarCodigoComision($comision);
        }
    }
}
