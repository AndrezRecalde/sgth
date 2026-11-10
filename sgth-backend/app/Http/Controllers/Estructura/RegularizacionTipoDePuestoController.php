<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\ReubicarContratoRequest;
use App\Http\Resources\Estructura\ContratoPorRegularizarResource;
use App\Http\Resources\Estructura\PuestoPorRegularizarResource;
use App\Http\Resources\Estructura\PuestoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Services\Estructura\RegularizacionTipoDePuestoService;
use Illuminate\Http\JsonResponse;

/**
 * «Por revisar» en Puestos y cargos: la regularización del tipo de puesto
 * (docs/diseno-manual-de-puestos.md, P9, PR 4). Propone; Talento Humano
 * confirma cada caso.
 */
class RegularizacionTipoDePuestoController extends Controller
{
    public function __construct(private readonly RegularizacionTipoDePuestoService $regularizacion)
    {
    }

    /** Los puestos que parecen de contrato y los contratos en un puesto de plantilla. */
    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'puestos'   => PuestoPorRegularizarResource::collection($this->regularizacion->puestosQueParecenDeContrato()),
            'contratos' => ContratoPorRegularizarResource::collection($this->regularizacion->contratosEnPuestoDePlantilla()),
        ], 'Lo que queda por revisar del tipo de puesto.');
    }

    public function pasarAContrato(int $puestoId): JsonResponse
    {
        $puesto = $this->regularizacion->pasarAContrato(Puesto::findOrFail($puestoId));

        return ApiResponse::ok(new PuestoResource($puesto), 'El puesto pasó a ser de contrato.');
    }

    public function reubicar(ReubicarContratoRequest $request, int $contratoId): JsonResponse
    {
        $contrato = $this->regularizacion->reubicar(
            ContratoServidor::findOrFail($contratoId),
            Puesto::findOrFail((int) $request->validated('puesto_destino_id')),
        );

        return ApiResponse::ok(
            ['id' => $contrato->id, 'puesto_id' => $contrato->puesto_id],
            'El contrato quedó en el puesto de contrato.',
        );
    }
}
