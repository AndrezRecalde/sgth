<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\BuscarServidoresRequest;
use App\Http\Resources\Expediente\ServidorBusquedaResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * El buscador de servidores de los formularios
 * (docs/plan-roles-y-accesos.md, PR 2).
 *
 * Se autoriza por `buscar-servidores` en la ruta, no por ServidorPolicy: elegir
 * a una persona no es abrir su expediente.
 */
class BuscarServidorController extends Controller
{
    /** Cuántos se devuelven si no se pide otra cantidad. */
    private const POR_DEFECTO = 10;

    public function __construct(
        private readonly ExpedienteServiceInterface $expedienteService,
    ) {
    }

    public function index(BuscarServidoresRequest $request): JsonResponse
    {
        $servidores = $this->expedienteService->buscarServidores(
            $request->safe()->only(['search', 'unidad_administrativa_id', 'es_jefe', 'en_funciones']),
            (int) ($request->validated('per_page') ?? self::POR_DEFECTO),
        );

        return ApiResponse::ok(ServidorBusquedaResource::collection($servidores), 'Servidores encontrados.');
    }

    /** El que ya está elegido, para que el formulario enseñe su nombre. */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(
            new ServidorBusquedaResource($this->expedienteService->servidorParaBuscador($id)),
            'Servidor.',
        );
    }
}
