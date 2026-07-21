<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\EstadoCumplimientoNormativa;
use App\Services\Sso\CumplimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class CumplimientoNormativaController extends Controller
{
    public function __construct(
        private readonly CumplimientoService $cumplimientoService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'normativa_legal_sso_id' => ['required', 'integer', 'exists:normativa_legal_sso,id'],
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'estado' => ['required', new Enum(EstadoCumplimientoNormativa::class)],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $cumplimiento = $this->cumplimientoService->registrarCumplimiento($validated);
        return ApiResponse::created($cumplimiento, 'Cumplimiento registrado exitosamente.');
    }

    public function listaVerificacion(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
        ]);

        $lista = $this->cumplimientoService->listaVerificacion($request->string('periodo')->value());
        return ApiResponse::ok($lista, 'Lista de verificación de cumplimiento generada exitosamente.');
    }
}
