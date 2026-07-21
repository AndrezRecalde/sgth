<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreCapacitacionSsoRequest;
use App\Http\Requests\Sso\UpdateCapacitacionSsoRequest;
use App\Http\Resources\Sso\CapacitacionSsoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\CapacitacionSso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CapacitacionSsoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CapacitacionSso::class);
        $capacitaciones = $this->ssoService->listarCapacitaciones($request->all());
        return ApiResponse::paginado($capacitaciones, 'Capacitaciones SSO obtenidas exitosamente.');
    }

    public function store(StoreCapacitacionSsoRequest $request): JsonResponse
    {
        $registro = $this->ssoService->registrarCapacitacion($request->validated());
        return ApiResponse::created(new CapacitacionSsoResource($registro), 'Capacitación SSO registrada exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $capacitacion = $this->ssoService->obtenerCapacitacion($id);
        $this->authorize('view', $capacitacion);
        return ApiResponse::ok(new CapacitacionSsoResource($capacitacion), 'Capacitación SSO obtenida exitosamente.');
    }

    public function update(UpdateCapacitacionSsoRequest $request, int $id): JsonResponse
    {
        $registro = $this->ssoService->actualizarCapacitacion($id, $request->validated());
        return ApiResponse::ok(new CapacitacionSsoResource($registro), 'Capacitación SSO actualizada exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $capacitacion = $this->ssoService->obtenerCapacitacion($id);
        $this->authorize('delete', $capacitacion);
        $this->ssoService->eliminarCapacitacion($id);
        return ApiResponse::ok(null, 'Capacitación SSO eliminada exitosamente.');
    }
}
