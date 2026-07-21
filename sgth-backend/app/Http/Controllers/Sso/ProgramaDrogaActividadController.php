<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\FaseProgramaDrogas;
use App\Services\Sso\ProgramaDrogasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class ProgramaDrogaActividadController extends Controller
{
    public function __construct(
        private readonly ProgramaDrogasService $programaDrogasService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actividades = $this->programaDrogasService->listarActividades($request->all());
        return ApiResponse::ok($actividades, 'Actividades del programa de drogas obtenidas exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fase' => ['required', new Enum(FaseProgramaDrogas::class)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
        ]);

        $actividad = $this->programaDrogasService->registrarActividad($validated);
        return ApiResponse::created($actividad, 'Actividad registrada exitosamente.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fase' => ['sometimes', 'required', new Enum(FaseProgramaDrogas::class)],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'activo' => ['boolean'],
        ]);

        $actividad = $this->programaDrogasService->actualizarActividad($id, $validated);
        return ApiResponse::ok($actividad, 'Actividad actualizada exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->programaDrogasService->eliminarActividad($id);
        return ApiResponse::ok(null, 'Actividad eliminada exitosamente.');
    }
}
