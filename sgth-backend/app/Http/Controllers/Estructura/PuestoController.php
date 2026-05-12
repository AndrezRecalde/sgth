<?php

namespace App\Http\Controllers\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\StorePuestoRequest;
use App\Http\Requests\Estructura\UpdatePuestoRequest;
use App\Http\Resources\Estructura\PuestoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PuestoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Puesto::class);
        $puestos = $this->estructuraService->listarPuestos($request->all());
        return ApiResponse::ok(PuestoResource::collection($puestos), 'Puestos orgánicos obtenidos exitosamente');
    }

    public function store(StorePuestoRequest $request): JsonResponse
    {
        $puesto = $this->estructuraService->crearPuesto($request->validated());
        return ApiResponse::created(new PuestoResource($puesto), 'Puesto orgánico creado exitosamente');
    }

    public function show(Puesto $puesto): JsonResponse
    {
        $this->authorize('view', $puesto);
        $puestoModel = $this->estructuraService->obtenerPuesto($puesto->id);
        return ApiResponse::ok(new PuestoResource($puestoModel), 'Puesto orgánico obtenido exitosamente');
    }

    public function update(UpdatePuestoRequest $request, Puesto $puesto): JsonResponse
    {
        $puestoModel = $this->estructuraService->actualizarPuesto($puesto->id, $request->validated());
        return ApiResponse::ok(new PuestoResource($puestoModel), 'Puesto orgánico actualizado exitosamente');
    }

    public function destroy(Puesto $puesto): JsonResponse
    {
        $this->authorize('delete', $puesto);
        $this->estructuraService->eliminarPuesto($puesto->id);
        return ApiResponse::ok(null, 'Puesto orgánico eliminado exitosamente');
    }
}
