<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreInspeccionSsoRequest;
use App\Http\Requests\Sso\UpdateInspeccionSsoRequest;
use App\Http\Resources\Sso\InspeccionSsoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\InspeccionSso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InspeccionSsoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InspeccionSso::class);
        $inspecciones = $this->ssoService->listarInspecciones($request->all());
        return ApiResponse::paginado($inspecciones, 'Inspecciones SSO obtenidas exitosamente.');
    }

    public function store(StoreInspeccionSsoRequest $request): JsonResponse
    {
        $registro = $this->ssoService->registrarInspeccion($request->validated());
        return ApiResponse::created(new InspeccionSsoResource($registro), 'Inspección SSO registrada exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $inspeccion = $this->ssoService->obtenerInspeccion($id);
        $this->authorize('view', $inspeccion);
        return ApiResponse::ok(new InspeccionSsoResource($inspeccion), 'Inspección SSO obtenida exitosamente.');
    }

    public function update(UpdateInspeccionSsoRequest $request, int $id): JsonResponse
    {
        $registro = $this->ssoService->actualizarInspeccion($id, $request->validated());
        return ApiResponse::ok(new InspeccionSsoResource($registro), 'Inspección SSO actualizada exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $inspeccion = $this->ssoService->obtenerInspeccion($id);
        $this->authorize('delete', $inspeccion);
        $this->ssoService->eliminarInspeccion($id);
        return ApiResponse::ok(null, 'Inspección SSO eliminada exitosamente.');
    }
}
