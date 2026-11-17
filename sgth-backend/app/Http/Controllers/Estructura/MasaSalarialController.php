<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\MasaSalarialRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Estructura\MasaSalarialService;
use Illuminate\Http\JsonResponse;

class MasaSalarialController extends Controller
{
    public function __construct(private MasaSalarialService $masa)
    {
    }

    /**
     * La masa salarial referencial a una fecha de corte (por omisión, hoy): la
     * suma de las remuneraciones registradas en los vínculos vigentes, por
     * tipo de nombramiento, régimen y unidad, con su detalle. No es lo pagado.
     */
    public function resumen(MasaSalarialRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->masa->al($request->corte(), $request->filtros()),
            'Masa salarial referencial.'
        );
    }
}
