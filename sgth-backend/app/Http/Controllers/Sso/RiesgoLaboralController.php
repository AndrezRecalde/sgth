<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
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

    public function store(StoreRiesgoLaboralRequest $request): JsonResponse
    {
        $method = 'registrar' . str_replace('Sso', '', 'RiesgoLaboral');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'registrarRiesgoLaboral';
        }
        $registro = $this->ssoService->$method($request->validated());
        return ApiResponse::created(new RiesgoLaboralResource($registro), 'Riesgo laboral registrado exitosamente.');
    }

    public function update(UpdateRiesgoLaboralRequest $request, int $id): JsonResponse
    {
        $method = 'actualizar' . str_replace('Sso', '', 'RiesgoLaboral');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'actualizarRiesgoLaboral';
        }
        $registro = $this->ssoService->$method($id, $request->validated());
        return ApiResponse::ok(new RiesgoLaboralResource($registro), 'Riesgo laboral actualizado exitosamente.');
    }
}
