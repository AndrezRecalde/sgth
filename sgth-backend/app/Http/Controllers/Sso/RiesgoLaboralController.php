<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\ListarRiesgosLaboralesRequest;
use App\Http\Requests\Sso\StoreRiesgoLaboralRequest;
use App\Http\Requests\Sso\UpdateRiesgoLaboralRequest;
use App\Http\Resources\Sso\RiesgoLaboralResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;

final class RiesgoLaboralController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(ListarRiesgosLaboralesRequest $request): JsonResponse
    {
        // La autorización vive en `ListarRiesgosLaboralesRequest::authorize()`, que Laravel
        // ejecuta ANTES de validar: así un filtro inválido no delata el
        // endpoint a quien no puede consultarlo (403 antes que 422).
        $riesgos = $this->ssoService->listarRiesgosLaborales($request->filtros());
        return ApiResponse::paginado($riesgos, 'Riesgos laborales obtenidos exitosamente.');
    }

    public function store(StoreRiesgoLaboralRequest $request): JsonResponse
    {
        $registro = $this->ssoService->registrarRiesgoLaboral($request->validated());
        return ApiResponse::created(new RiesgoLaboralResource($registro), 'Riesgo laboral registrado exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $riesgo = $this->ssoService->obtenerRiesgoLaboral($id);
        $this->authorize('view', $riesgo);
        return ApiResponse::ok(new RiesgoLaboralResource($riesgo), 'Riesgo laboral obtenido exitosamente.');
    }

    public function update(UpdateRiesgoLaboralRequest $request, int $id): JsonResponse
    {
        $registro = $this->ssoService->actualizarRiesgoLaboral($id, $request->validated());
        return ApiResponse::ok(new RiesgoLaboralResource($registro), 'Riesgo laboral actualizado exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $riesgo = $this->ssoService->obtenerRiesgoLaboral($id);
        $this->authorize('delete', $riesgo);
        $this->ssoService->eliminarRiesgoLaboral($id);
        return ApiResponse::ok(null, 'Riesgo laboral eliminado exitosamente.');
    }
}
