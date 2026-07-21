<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\EppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PuestoEppController extends Controller
{
    public function __construct(
        private readonly EppService $eppService,
    ) {}

    public function index(int $puestoId): JsonResponse
    {
        $equipos = $this->eppService->listarEquiposPorPuesto($puestoId);
        return ApiResponse::ok($equipos, 'Equipos de protección requeridos obtenidos exitosamente.');
    }

    public function store(Request $request, int $puestoId): JsonResponse
    {
        $validated = $request->validate([
            'equipo_proteccion_id' => ['required', 'integer', 'exists:equipos_proteccion,id'],
            'cantidad_requerida' => ['nullable', 'integer', 'min:1'],
            'frecuencia_reposicion_meses' => ['nullable', 'integer', 'min:1'],
        ]);
        $validated['puesto_id'] = $puestoId;

        $asignacion = $this->eppService->asignarEquipoAPuesto($validated);
        return ApiResponse::created($asignacion, 'Equipo de protección asignado al puesto exitosamente.');
    }

    public function destroy(int $puestoId, int $id): JsonResponse
    {
        $this->eppService->eliminarAsignacion($id);
        return ApiResponse::ok(null, 'Asignación eliminada exitosamente.');
    }
}
