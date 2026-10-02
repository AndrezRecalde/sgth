<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\StoreEquipoProteccionRequest;
use App\Http\Requests\Sso\UpdateEquipoProteccionRequest;
use App\Http\Resources\Sso\EquipoProteccionResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\EquipoProteccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EquipoProteccionController extends Controller
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipoProteccion::class);
        $equipos = $this->ssoService->listarEquiposProteccion($request->all());
        return ApiResponse::paginadoDe(
            $equipos,
            EquipoProteccionResource::collection($equipos->items()),
            'Equipos de protección obtenidos exitosamente.',
        );
    }

    /**
     * El catálogo completo de equipos activos, para los desplegables.
     *
     * Va en su propia ruta y no como un parámetro de `index` porque `index`
     * pagina y esto no: los tres formularios que eligen un equipo necesitan
     * ofrecerlos todos, y leyendo la primera página se quedaban con quince.
     */
    public function catalogo(): JsonResponse
    {
        $this->authorize('viewAny', EquipoProteccion::class);

        return ApiResponse::ok(
            $this->ssoService->catalogoEquiposProteccion(),
            'Catálogo de equipos de protección obtenido exitosamente.',
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
