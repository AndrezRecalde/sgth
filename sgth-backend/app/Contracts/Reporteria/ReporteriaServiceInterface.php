<?php

namespace App\Contracts\Reporteria;

interface ReporteriaServiceInterface
{
    /**
     * Obtiene los KPIs agrupados en tiempo real con soporte de caché.
     *
     * @return array
     */
    public function obtenerKpisDashboard(): array;
}
