<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreAccidenteTrabajoRequest;
use App\Http\Requests\Sso\UpdateAccidenteTrabajoRequest;
use App\Http\Resources\Sso\AccidenteTrabajoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\AccidenteTrabajo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccidenteTrabajoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccidenteTrabajo::class);
        $accidentes = $this->ssoService->listarAccidentes($request->all());
        return ApiResponse::paginado($accidentes, 'Accidentes de trabajo obtenidos exitosamente.');
    }

    public function store(StoreAccidenteTrabajoRequest $request): JsonResponse
    {
        $registro = $this->ssoService->registrarAccidente($request->validated());
        return ApiResponse::created(new AccidenteTrabajoResource($registro), 'Accidente de trabajo registrado exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $accidente = $this->ssoService->obtenerAccidente($id);
        $this->authorize('view', $accidente);
        return ApiResponse::ok(new AccidenteTrabajoResource($accidente), 'Accidente de trabajo obtenido exitosamente.');
    }

    public function update(UpdateAccidenteTrabajoRequest $request, int $id): JsonResponse
    {
        $registro = $this->ssoService->actualizarAccidente($id, $request->validated());
        return ApiResponse::ok(new AccidenteTrabajoResource($registro), 'Accidente de trabajo actualizado exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $accidente = $this->ssoService->obtenerAccidente($id);
        $this->authorize('delete', $accidente);
        $this->ssoService->eliminarAccidente($id);
        return ApiResponse::ok(null, 'Accidente de trabajo eliminado exitosamente.');
    }
}
