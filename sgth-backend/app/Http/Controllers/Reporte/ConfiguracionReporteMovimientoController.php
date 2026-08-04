<?php

namespace App\Http\Controllers\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\UpdateConfiguracionReporteMovimientoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use Illuminate\Http\JsonResponse;

class ConfiguracionReporteMovimientoController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(
            ConfiguracionReporteMovimiento::orderBy('tipo_movimiento')->get(),
            'Configuración de reportabilidad SIITH/SUT por tipo de movimiento.'
        );
    }

    public function update(
        UpdateConfiguracionReporteMovimientoRequest $request,
        ConfiguracionReporteMovimiento $configuracion
    ): JsonResponse {
        $configuracion->update($request->validated());

        return ApiResponse::ok($configuracion->fresh(), 'Configuración actualizada.');
    }
}
