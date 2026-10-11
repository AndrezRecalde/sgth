<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\ReintegrarAscensoRequest;
use App\Http\Resources\Expediente\MovimientoPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ContratoServidor;
use App\Services\Expediente\PeriodoDePruebaService;
use App\Services\Expediente\ReintegroService;
use Illuminate\Http\JsonResponse;

/**
 * Quién está en período de prueba y cómo termina (LOSEP 17 b.5; diseño de
 * Acciones de Personal, 9.2; fases 3.2 y 3.3): el nombramiento definitivo, o el
 * reintegro del ascendido que no la superó.
 */
class PeriodoDePruebaController extends Controller
{
    public function __construct(
        private PeriodoDePruebaService $periodos,
        private ReintegroService $reintegros,
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->periodos->listar(), 'Períodos de prueba en curso.');
    }

    /** Prepara en borrador el nombramiento definitivo, cumplida la prueba. */
    public function nombramientoDefinitivo(ContratoServidor $contrato): JsonResponse
    {
        $this->authorize('actualizar', $contrato->servidor);

        return ApiResponse::created(
            new MovimientoPersonalResource($this->periodos->preparar($contrato)),
            'Nombramiento definitivo preparado en borrador.'
        );
    }

    /** Prepara en borrador el reintegro del ascendido a su puesto anterior. */
    public function reintegroDelAscenso(ReintegrarAscensoRequest $request, ContratoServidor $contrato): JsonResponse
    {
        $this->authorize('actualizar', $contrato->servidor);

        return ApiResponse::created(
            new MovimientoPersonalResource($this->reintegros->prepararDelAscenso($contrato, $request->validated())),
            'Reintegro al puesto anterior preparado en borrador.'
        );
    }
}
