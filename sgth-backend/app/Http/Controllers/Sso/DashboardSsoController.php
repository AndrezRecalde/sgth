<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\DashboardSsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardSsoController extends Controller
{
    public function __construct(
        private readonly DashboardSsoService $dashboardSsoService,
    ) {}

    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        $resumen = $this->dashboardSsoService->resumen(
            $request->string('periodo')->value(),
            $request->integer('unidad_administrativa_id') ?: null,
        );

        return ApiResponse::ok($resumen, 'Resumen del dashboard SSO calculado exitosamente.');
    }
}
