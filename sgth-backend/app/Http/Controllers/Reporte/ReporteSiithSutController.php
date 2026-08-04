<?php

namespace App\Http\Controllers\Reporte;

use App\Contracts\Reporte\ReporteSiithSutServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteSiithSutController extends Controller
{
    public function __construct(
        private readonly ReporteSiithSutServiceInterface $reporteService,
    ) {
    }

    public function movimientos(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'portal'      => ['required', 'in:siith,sut'],
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date'],
        ]);

        return ApiResponse::ok($this->reporteService->movimientosReportables($filtros));
    }

    public function mensual(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio'   => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes'    => ['required', 'integer', 'min:1', 'max:12'],
            'portal' => ['required', 'in:siith,sut'],
        ]);

        return ApiResponse::ok(
            $this->reporteService->reporteMensual($datos['anio'], $datos['mes'], $datos['portal'])
        );
    }
}
