<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IndicadoresSsoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function reactivos(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        $indicadores = $this->ssoService->calcularIndicadoresMrl(
            $request->string('periodo')->value(),
            $request->integer('unidad_administrativa_id') ?: null,
        );

        return ApiResponse::ok($indicadores, 'Índices reactivos CD 513 calculados exitosamente.');
    }

    public function proactivos(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        $indicadores = $this->ssoService->calcularIndicadoresProactivos(
            $request->string('periodo')->value(),
            $request->integer('unidad_administrativa_id') ?: null,
        );

        return ApiResponse::ok($indicadores, 'Índices proactivos calculados exitosamente.');
    }
}
