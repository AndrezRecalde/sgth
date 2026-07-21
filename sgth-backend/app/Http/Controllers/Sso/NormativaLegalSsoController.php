<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\TipoNormativaLegal;
use App\Services\Sso\CumplimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class NormativaLegalSsoController extends Controller
{
    public function __construct(
        private readonly CumplimientoService $cumplimientoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $normativas = $this->cumplimientoService->listarNormativas($request->all());
        return ApiResponse::ok($normativas, 'Normativa legal obtenida exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', new Enum(TipoNormativaLegal::class)],
            'fecha_vigencia' => ['nullable', 'date'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
        ]);

        $normativa = $this->cumplimientoService->registrarNormativa($validated);
        return ApiResponse::created($normativa, 'Normativa legal registrada exitosamente.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo' => ['sometimes', 'required', new Enum(TipoNormativaLegal::class)],
            'fecha_vigencia' => ['nullable', 'date'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'activo' => ['boolean'],
        ]);

        $normativa = $this->cumplimientoService->actualizarNormativa($id, $validated);
        return ApiResponse::ok($normativa, 'Normativa legal actualizada exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->cumplimientoService->eliminarNormativa($id);
        return ApiResponse::ok(null, 'Normativa legal eliminada exitosamente.');
    }
}
