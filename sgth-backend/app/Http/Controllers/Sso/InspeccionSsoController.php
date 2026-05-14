<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreInspeccionSsoRequest;
use App\Http\Requests\Sso\UpdateInspeccionSsoRequest;
use App\Http\Resources\Sso\InspeccionSsoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;

final class InspeccionSsoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function store(StoreInspeccionSsoRequest $request): JsonResponse
    {
        $method = 'registrar' . str_replace('Sso', '', 'InspeccionSso');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'registrarInspeccionSso';
        }
        $registro = $this->ssoService->$method($request->validated());
        return ApiResponse::created(new InspeccionSsoResource($registro), 'Inspección SSO registrado exitosamente.');
    }

    public function update(UpdateInspeccionSsoRequest $request, int $id): JsonResponse
    {
        $method = 'actualizar' . str_replace('Sso', '', 'InspeccionSso');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'actualizarInspeccionSso';
        }
        $registro = $this->ssoService->$method($id, $request->validated());
        return ApiResponse::ok(new InspeccionSsoResource($registro), 'Inspección SSO actualizado exitosamente.');
    }
}
