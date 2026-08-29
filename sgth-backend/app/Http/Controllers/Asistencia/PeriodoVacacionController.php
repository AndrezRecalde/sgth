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
     * Qué cambiaría al forzar el recálculo de un período cerrado.
     *
     * No escribe nada: alimenta el diálogo de confirmación para que diga el
     * saldo concreto de antes y de después. La consecuencia tiene que verse
     * antes de aceptarla.
     */
    public function previsualizarRecalculo(Request $request, int $servidorId): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $servidor = \App\Models\Expediente\Servidor::findOrFail($servidorId);

        $previsualizacion = $this->periodoService->previsualizarRecalculo(
            $servidor, (int) $datos['anio']
        );

        if (! $previsualizacion) {
            return ApiResponse::error(
                "No existe un período {$datos['anio']} para este servidor.",
                null, 404
            );
        }

        return ApiResponse::ok($previsualizacion, 'Previsualización del recálculo.');
    }

    /**
     * Recalcula un período YA CERRADO, a sabiendas.
     *
     * Va por su propia ruta y no como una bandera de `generar`: alterar un
     * saldo certificado tiene que ser una decisión explícita sobre un servidor
     * y un año concretos, nunca el efecto colateral de una operación masiva.
     * El servicio lo registra en la bitácora con los valores de antes y después.
     */
    public function recalcularCerrado(Request $request, int $servidorId): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $servidor = \App\Models\Expediente\Servidor::findOrFail($servidorId);

        $periodo = PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $datos['anio'])
            ->first();

        if (! $periodo) {
            return ApiResponse::error(
                "No existe un período {$datos['anio']} para este servidor.",
                null, 404
            );
        }

        if ($periodo->estado === 'abierto') {
            return ApiResponse::error(
                'Este período está abierto: se recalcula con la generación normal, sin forzar.',
                null, 422
            );
        }

        $saldoAnterior = (float) $periodo->dias_saldo;

        $actualizado = $this->periodoService->generarPeriodo(
            $servidor, (int) $datos['anio'], forzar: true
        );

        return ApiResponse::ok(
            $actualizado,
            sprintf(
                'Período %d recalculado. El saldo pasó de %.2f a %.2f días.',
                $datos['anio'], $saldoAnterior, (float) $actualizado->dias_saldo
            )
        );
    }

    /**
     * Generar períodos para todos los servidores (admin).
     *
     * Nunca fuerza: los períodos cerrados se devuelven intactos. Es una
     * operación de rutina y tiene que ser inofensiva.
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
