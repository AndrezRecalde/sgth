<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\EstadisticasDispensarioServiceInterface;
use Illuminate\Http\JsonResponse;

class DashboardDispensarioController extends Controller
{
    private EstadisticasDispensarioServiceInterface $estadisticasService;

    public function __construct(EstadisticasDispensarioServiceInterface $estadisticasService)
    {
        $this->estadisticasService = $estadisticasService;
    }

    /**
     * Retorna los KPIs integrales del dispensario.
     */
    public function kpis(): JsonResponse
    {
        $kpis = $this->estadisticasService->obtenerKpisMensuales();
        
        return ApiResponse::ok($kpis, 'Estadísticas del dispensario generadas exitosamente');
    }
}
