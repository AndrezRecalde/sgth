<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\MovimientoPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ContratoServidor;
use App\Services\Expediente\PeriodoDePruebaService;
use Illuminate\Http\JsonResponse;

/**
 * Quién está en período de prueba y su nombramiento definitivo (LOSEP 17 b.5;
 * diseño de Acciones de Personal, 9.2; fase 3.2).
 */
class PeriodoDePruebaController extends Controller
{
    public function __construct(private PeriodoDePruebaService $periodos)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->periodos->listar(), 'Períodos de prueba en curso.');
    }

    /** Prepara en borrador el nombramiento definitivo, cumplidos los tres meses. */
    public function nombramientoDefinitivo(ContratoServidor $contrato): JsonResponse
    {
        $this->authorize('actualizar', $contrato->servidor);

        return ApiResponse::created(
            new MovimientoPersonalResource($this->periodos->preparar($contrato)),
            'Nombramiento definitivo preparado en borrador.'
        );
    }
}
