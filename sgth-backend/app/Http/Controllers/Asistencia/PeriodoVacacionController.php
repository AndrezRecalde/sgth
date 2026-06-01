<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PeriodoVacacion;
use App\Services\Asistencia\PeriodoVacacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodoVacacionController extends Controller
{
    public function __construct(
        private PeriodoVacacionService $periodoService
    ) {}

    /**
     * Resumen de períodos y saldo de un servidor.
     */
    public function resumen(int $servidorId): JsonResponse
    {
        $resumen = $this->periodoService->resumen($servidorId);
        return ApiResponse::ok($resumen, 'Resumen de períodos de vacaciones.');
    }

    /**
     * Generar período del año actual para un servidor.
     */
    public function generar(Request $request, int $servidorId): JsonResponse
    {
        $anio    = $request->input('anio', now()->year);
        $servidor = \App\Models\Expediente\Servidor::findOrFail($servidorId);
        $periodo  = $this->periodoService->generarPeriodo($servidor, (int)$anio);

        return ApiResponse::ok($periodo, "Período {$anio} generado correctamente.");
    }

    /**
     * Generar períodos para todos los servidores (admin).
     */
    public function generarTodos(Request $request): JsonResponse
    {
        $anio      = $request->input('anio', now()->year);
        $resultados = $this->periodoService->generarPeriodosAnuales((int)$anio);

        return ApiResponse::ok(
            ['generados' => $resultados->count()],
            "Períodos {$anio} generados para {$resultados->count()} servidores."
        );
    }
}
