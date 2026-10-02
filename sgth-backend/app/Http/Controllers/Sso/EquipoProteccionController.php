<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\ListarEquiposProteccionRequest;
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

    public function index(ListarEquiposProteccionRequest $request): JsonResponse
    {
        // La autorización vive en `ListarEquiposProteccionRequest::authorize()`, que Laravel
        // ejecuta ANTES de validar: así un filtro inválido no delata el
        // endpoint a quien no puede consultarlo (403 antes que 422).
        $equipos = $this->ssoService->listarEquiposProteccion($request->filtros());
        return ApiResponse::paginadoDe(
            $equipos,
            EquipoProteccionResource::collection($equipos->items()),
            'Equipos de protección obtenidos exitosamente.',
        );
    }

    public function store(StoreEquipoProteccionRequest $request): JsonResponse
    {
        $registro = $this->ssoService->registrarEquipoProteccion($request->validated());
        return ApiResponse::created(new EquipoProteccionResource($registro), 'Equipo de protección registrado exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $equipo = $this->ssoService->obtenerEquipoProteccion($id);
        $this->authorize('view', $equipo);
        return ApiResponse::ok(new EquipoProteccionResource($equipo), 'Equipo de protección obtenido exitosamente.');
    }

    public function update(UpdateEquipoProteccionRequest $request, int $id): JsonResponse
    {
        $registro = $this->ssoService->actualizarEquipoProteccion($id, $request->validated());
        return ApiResponse::ok(new EquipoProteccionResource($registro), 'Equipo de protección actualizado exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $equipo = $this->ssoService->obtenerEquipoProteccion($id);
        $this->authorize('delete', $equipo);
        $this->ssoService->eliminarEquipoProteccion($id);
        return ApiResponse::ok(null, 'Equipo de protección eliminado exitosamente.');
    }
}
