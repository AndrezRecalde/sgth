<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\EstadoActividadPrograma;
use App\Services\Sso\ProgramaDrogasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class ProgramaDrogaSeguimientoController extends Controller
{
    public function __construct(
        private readonly ProgramaDrogasService $programaDrogasService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programa_droga_actividad_id' => ['required', 'integer', 'exists:programa_drogas_actividades,id'],
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'estado' => ['required', new Enum(EstadoActividadPrograma::class)],
            'fecha_ejecucion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $seguimiento = $this->programaDrogasService->registrarSeguimiento($validated);
        return ApiResponse::created($seguimiento, 'Seguimiento registrado exitosamente.');
    }

    public function listaSeguimiento(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
        ]);

        $lista = $this->programaDrogasService->listaSeguimiento($request->string('periodo')->value());
        return ApiResponse::ok($lista, 'Matriz de seguimiento del programa de drogas generada exitosamente.');
    }
}
