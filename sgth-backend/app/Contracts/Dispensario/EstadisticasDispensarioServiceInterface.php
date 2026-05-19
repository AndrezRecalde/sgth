<?php
namespace App\Contracts\Dispensario;

interface EstadisticasDispensarioServiceInterface
{
    public function obtenerKpisMensuales(): array;
}
