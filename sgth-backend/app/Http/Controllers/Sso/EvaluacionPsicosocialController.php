<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\PsicosocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EvaluacionPsicosocialController extends Controller
{
    public function __construct(
        private readonly PsicosocialService $psicosocialService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['periodo']);
        $campanias = $this->psicosocialService->listarCampanias($filtros);
        return ApiResponse::ok($campanias, 'Campañas de evaluación psicosocial obtenidas exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'fecha_apertura' => ['required', 'date'],
            'fecha_cierre' => ['nullable', 'date', 'after_or_equal:fecha_apertura'],
        ]);

        $campania = $this->psicosocialService->crearCampania($validated);
        return ApiResponse::created($campania, 'Campaña de evaluación psicosocial creada exitosamente.');
    }

    public function cerrar(int $id): JsonResponse
    {
        $campania = $this->psicosocialService->cerrarCampania($id);
        return ApiResponse::ok($campania, 'Campaña cerrada exitosamente.');
    }

    public function resultados(int $id): JsonResponse
    {
        $resultados = $this->psicosocialService->resultadosAgregados($id);
        return ApiResponse::ok($resultados, 'Resultados agregados obtenidos exitosamente.');
    }
}
