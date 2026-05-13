<?php

namespace App\Http\Controllers\Reporteria;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Reporteria\ReporteriaServiceInterface;

class DashboardController extends Controller
{
    public function __construct(private readonly ReporteriaServiceInterface $service)
    {
    }

    public function kpis()
    {
        // Delega la obtención de todos los KPIs al servicio (cumpliendo 0 lógica de negocio)
        $kpis = $this->service->obtenerKpisDashboard();
        
        return ApiResponse::ok($kpis, 'KPIs del dashboard ejecutivo obtenidos correctamente.');
    }
}
