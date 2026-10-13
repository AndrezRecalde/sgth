<?php

namespace App\Http\Controllers\InventarioTi;

use App\Exports\ReporteUsoPrestamosExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\InventarioTi\Prestamos\ReporteUsoPrestamoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** El uso de cada equipo de préstamo en un rango (fase 2.4), en pantalla o en Excel. */
final class ReporteUsoPrestamoController extends Controller
{
    public function __construct(private readonly ReporteUsoPrestamoService $reporte) {}

    public function index(Request $request): JsonResponse
    {
        $rango = $this->rango($request);

        return ApiResponse::ok($this->reporte->generar($rango['desde'], $rango['hasta']), 'Uso de los equipos de préstamo.');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $rango = $this->rango($request);
        $datos = $this->reporte->generar($rango['desde'], $rango['hasta']);

        return Excel::download(new ReporteUsoPrestamosExport($datos), "uso-equipos-prestamo-{$datos['desde']}-a-{$datos['hasta']}.xlsx");
    }

    /** @return array{desde: string, hasta: string} */
    private function rango(Request $request): array
    {
        return $request->validate(
            ['desde' => ['required', 'date'], 'hasta' => ['required', 'date', 'after_or_equal:desde']],
            [],
            ['desde' => 'desde', 'hasta' => 'hasta'],
        );
    }
}
