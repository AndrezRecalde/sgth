<?php

namespace App\Http\Controllers\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\BuscarPuestosRequest;
use App\Http\Resources\Estructura\PuestoBusquedaResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * El buscador de puestos de los formularios
 * (docs/plan-roles-y-accesos.md, PR 12).
 *
 * Se autoriza en la ruta y no con PuestoPolicy, que pide `ver-estructura`: el
 * técnico de SSO asigna riesgos y EPP a un puesto sin ver la estructura, y
 * recibía 403. Elegir un puesto no es abrir el listado de TH.
 */
class BuscarPuestoController extends Controller
{
    /** Cuántos se devuelven si no se pide otra cantidad. */
    private const POR_DEFECTO = 10;

    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService,
    ) {
    }

    public function index(BuscarPuestosRequest $request): JsonResponse
    {
        $puestos = $this->estructuraService->buscarPuestos(
            $request->validated('search'),
            (int) ($request->validated('per_page') ?? self::POR_DEFECTO),
        );

        return ApiResponse::ok(PuestoBusquedaResource::collection($puestos), 'Puestos encontrados.');
    }

    /** El que ya está elegido, para que el formulario enseñe su nombre. */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(
            new PuestoBusquedaResource($this->estructuraService->puestoParaBuscador($id)),
            'Puesto.',
        );
    }
}
