<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\CatalogoInventarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreContratoVigenciaRequest;
use App\Http\Requests\InventarioTi\UpdateContratoVigenciaRequest;
use App\Http\Resources\InventarioTi\ContratoVigenciaResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\ContratoVigencia;
use Illuminate\Http\JsonResponse;

/** Los contratos de vigencia tecnológica y su cronograma de mantenimientos. */
final class ContratoVigenciaController extends Controller
{
    public function __construct(private readonly CatalogoInventarioServiceInterface $catalogos) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok(
            ContratoVigenciaResource::collection($this->catalogos->listarContratos()),
            'Contratos de vigencia tecnológica.'
        );
    }

    public function show(ContratoVigencia $contrato): JsonResponse
    {
        $contrato->load(['administrador:id,nombre,apellido,cedula', 'cronograma'])->loadCount('bienes');

        return ApiResponse::ok(new ContratoVigenciaResource($contrato), 'Contrato de vigencia.');
    }

    public function store(StoreContratoVigenciaRequest $request): JsonResponse
    {
        $contrato = $this->catalogos->guardarContrato(null, $request->validated());

        return ApiResponse::created(new ContratoVigenciaResource($contrato), 'Contrato registrado.');
    }

    public function update(UpdateContratoVigenciaRequest $request, ContratoVigencia $contrato): JsonResponse
    {
        $contrato = $this->catalogos->guardarContrato($contrato, $request->validated());

        return ApiResponse::ok(new ContratoVigenciaResource($contrato), 'Contrato actualizado.');
    }

    public function destroy(ContratoVigencia $contrato): JsonResponse
    {
        $this->catalogos->eliminarContrato($contrato);

        return ApiResponse::ok(null, 'Contrato eliminado.');
    }
}
