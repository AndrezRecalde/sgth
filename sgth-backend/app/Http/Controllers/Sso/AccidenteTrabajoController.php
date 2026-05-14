<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreAccidenteTrabajoRequest;
use App\Http\Requests\Sso\UpdateAccidenteTrabajoRequest;
use App\Http\Resources\Sso\AccidenteTrabajoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use Illuminate\Http\JsonResponse;

final class AccidenteTrabajoController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function store(StoreAccidenteTrabajoRequest $request): JsonResponse
    {
        $method = 'registrar' . str_replace('Sso', '', 'AccidenteTrabajo');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'registrarAccidenteTrabajo';
        }
        $registro = $this->ssoService->$method($request->validated());
        return ApiResponse::created(new AccidenteTrabajoResource($registro), 'Accidente de trabajo registrado exitosamente.');
    }

    public function update(UpdateAccidenteTrabajoRequest $request, int $id): JsonResponse
    {
        $method = 'actualizar' . str_replace('Sso', '', 'AccidenteTrabajo');
        if (!method_exists($this->ssoService, $method)) {
            $method = 'actualizarAccidenteTrabajo';
        }
        $registro = $this->ssoService->$method($id, $request->validated());
        return ApiResponse::ok(new AccidenteTrabajoResource($registro), 'Accidente de trabajo actualizado exitosamente.');
    }
}
