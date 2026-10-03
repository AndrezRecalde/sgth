<?php

namespace App\Contracts\Dispensario;

use Carbon\CarbonInterface;

interface EstadisticasDispensarioServiceInterface
{
    /**
     * Las cifras del Dispensario entre dos fechas (inclusive), con la
     * comparación contra el período anterior del mismo largo.
     */
    public function obtenerKpis(CarbonInterface $desde, CarbonInterface $hasta): array;
}
