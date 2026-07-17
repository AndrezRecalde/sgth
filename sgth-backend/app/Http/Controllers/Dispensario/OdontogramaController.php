<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\AnularOdontogramaProcedimientoRequest;
use App\Http\Requests\Dispensario\StoreOdontogramaProcedimientoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\OdontogramaProcedimiento;
use App\Services\Dispensario\OdontogramaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OdontogramaController extends Controller
{
    public function __construct(
        private readonly OdontogramaService $odontogramaService
    ) {}

    public function show(Request $request, int $historiaClinicaId): JsonResponse
    {
        $odontograma = $this->odontogramaService->obtenerPorHistoriaClinica(
            $historiaClinicaId,
            $request->user()->id
        );

        return ApiResponse::ok($odontograma);
    }

    public function registrarProcedimiento(StoreOdontogramaProcedimientoRequest $request): JsonResponse
    {
        $procedimiento = $this->odontogramaService->registrarProcedimiento(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($procedimiento, 'Procedimiento registrado correctamente.');
    }

    public function historialPieza(int $piezaId): JsonResponse
    {
        $historial = $this->odontogramaService->historialPorPieza($piezaId);

        return ApiResponse::ok($historial);
    }

    /**
     * Anula un procedimiento por error de registro. Solo lo puede anular
     * quien lo registró, y únicamente si pertenece a la consulta que está
     * abierta ahora mismo (o, si no tiene consulta asociada, si fue
     * registrado el mismo día) — evita que se reescriba el historial de
     * visitas pasadas.
     */
    public function anularProcedimiento(
        AnularOdontogramaProcedimientoRequest $request,
        int $id
    ): JsonResponse {
        $procedimiento = OdontogramaProcedimiento::findOrFail($id);

        if ($procedimiento->anulado_en) {
            return ApiResponse::error(
                'Este procedimiento ya fue anulado.', null, 422
            );
        }

        if ($procedimiento->realizado_por !== $request->user()->id) {
            return ApiResponse::error(
                'Solo quien registró el procedimiento puede anularlo.', null, 403
            );
        }

        $mismaConsulta = $procedimiento->consulta_medica_id !== null
            ? $procedimiento->consulta_medica_id === $request->integer('consulta_medica_id')
            : $procedimiento->created_at->isToday();

        if (! $mismaConsulta) {
            return ApiResponse::error(
                'Solo se puede anular un procedimiento registrado en la consulta actual.',
                null, 422
            );
        }

        $procedimiento = $this->odontogramaService->anularProcedimiento(
            $procedimiento,
            $request->validated('motivo_anulacion'),
            $request->user()->id
        );

        return ApiResponse::ok($procedimiento, 'Procedimiento anulado correctamente.');
    }
}
