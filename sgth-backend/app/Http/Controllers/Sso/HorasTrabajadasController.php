<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\ListarHorasTrabajadasRequest;
use App\Http\Resources\Sso\HorasTrabajadasPeriodoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use App\Services\Sso\PeriodoSso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HorasTrabajadasController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(ListarHorasTrabajadasRequest $request): JsonResponse
    {
        $registros = $this->ssoService->listarHorasTrabajadas($request->filtros());
        return ApiResponse::paginadoDe(
            $registros,
            HorasTrabajadasPeriodoResource::collection($registros->items()),
            'Horas trabajadas obtenidas exitosamente.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => PeriodoSso::reglas(),
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'total_horas' => ['required', 'integer', 'min:1'],
        ]);

        $registro = $this->ssoService->registrarHorasTrabajadas($validated);
        return ApiResponse::created($registro, 'Horas trabajadas registradas exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ssoService->eliminarHorasTrabajadas($id);
        return ApiResponse::ok(null, 'Registro eliminado exitosamente.');
    }
}
