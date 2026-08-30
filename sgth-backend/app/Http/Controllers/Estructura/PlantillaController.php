<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Estructura\PlantillaService;
use Illuminate\Http\JsonResponse;

class PlantillaController extends Controller
{
    public function __construct(private PlantillaService $plantilla)
    {
    }

    /**
     * Estado de la plantilla: plazas, ocupación y personal por modalidad.
     */
    public function resumen(): JsonResponse
    {
        return ApiResponse::ok(
            $this->plantilla->resumen(),
            'Resumen de la plantilla institucional.'
        );
    }
}
