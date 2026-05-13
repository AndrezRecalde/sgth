<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreCapacitacionSsoRequest;
use App\Http\Requests\Sso\UpdateCapacitacionSsoRequest;
use App\Http\Resources\Sso\CapacitacionSsoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;

final class CapacitacionSsoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function store(StoreCapacitacionSsoRequest $request): JsonResponse
    {
        $method = 'registrar' . str_replace('Sso', '', 'CapacitacionSso');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'registrarCapacitacionSso';
        }
        $registro = $this->ssoService->$method($request->validated());
        return ApiResponse::created(new CapacitacionSsoResource($registro), 'Capacitación SSO registrado exitosamente.');
    }

    public function update(UpdateCapacitacionSsoRequest $request, int $id): JsonResponse
    {
        $method = 'actualizar' . str_replace('Sso', '', 'CapacitacionSso');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'actualizarCapacitacionSso';
        }
        $registro = $this->ssoService->$method($id, $request->validated());
        return ApiResponse::ok(new CapacitacionSsoResource($registro), 'Capacitación SSO actualizado exitosamente.');
    }
}
