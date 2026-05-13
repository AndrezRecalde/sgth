<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreEquipoProteccionRequest;
use App\Http\Requests\Sso\UpdateEquipoProteccionRequest;
use App\Http\Resources\Sso\EquipoProteccionResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;

final class EquipoProteccionController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function store(StoreEquipoProteccionRequest $request): JsonResponse
    {
        $method = 'registrar' . str_replace('Sso', '', 'EquipoProteccion');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'registrarEquipoProteccion';
        }
        $registro = $this->ssoService->$method($request->validated());
        return ApiResponse::created(new EquipoProteccionResource($registro), 'Equipo de protección registrado exitosamente.');
    }

    public function update(UpdateEquipoProteccionRequest $request, int $id): JsonResponse
    {
        $method = 'actualizar' . str_replace('Sso', '', 'EquipoProteccion');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'actualizarEquipoProteccion';
        }
        $registro = $this->ssoService->$method($id, $request->validated());
        return ApiResponse::ok(new EquipoProteccionResource($registro), 'Equipo de protección actualizado exitosamente.');
    }
}
