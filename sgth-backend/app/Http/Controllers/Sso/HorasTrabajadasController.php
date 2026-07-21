<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HorasTrabajadasController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $registros = $this->ssoService->listarHorasTrabajadas($request->all());
        return ApiResponse::paginado($registros, 'Horas trabajadas obtenidas exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'total_horas' => ['required', 'integer', 'min:1'],
        ]);

        $registro = $this->ssoService->registrarHorasTrabajadas($validated);
        return ApiResponse::created($registro, 'Horas trabajadas registradas exitosamente.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'total_horas' => ['required', 'integer', 'min:1'],
        ]);

        $registro = $this->ssoService->actualizarHorasTrabajadas($id, $validated);
        return ApiResponse::ok($registro, 'Horas trabajadas actualizadas exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ssoService->eliminarHorasTrabajadas($id);
        return ApiResponse::ok(null, 'Registro eliminado exitosamente.');
    }
}
