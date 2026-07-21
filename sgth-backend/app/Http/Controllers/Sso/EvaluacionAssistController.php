<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\AssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EvaluacionAssistController extends Controller
{
    public function __construct(
        private readonly AssistService $assistService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['periodo']);
        $campanias = $this->assistService->listarCampanias($filtros);
        return ApiResponse::ok($campanias, 'Campañas de tamizaje ASSIST obtenidas exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'fecha_apertura' => ['required', 'date'],
            'fecha_cierre' => ['nullable', 'date', 'after_or_equal:fecha_apertura'],
        ]);

        $campania = $this->assistService->crearCampania($validated);
        return ApiResponse::created($campania, 'Campaña de tamizaje ASSIST creada exitosamente.');
    }

    public function cerrar(int $id): JsonResponse
    {
        $campania = $this->assistService->cerrarCampania($id);
        return ApiResponse::ok($campania, 'Campaña cerrada exitosamente.');
    }

    public function resultados(int $id): JsonResponse
    {
        $resultados = $this->assistService->resultadosAgregados($id);
        return ApiResponse::ok($resultados, 'Resultados agregados obtenidos exitosamente.');
    }
}
