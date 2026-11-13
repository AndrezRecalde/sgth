<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Estructura\CompletitudDePerfilesService;
use Illuminate\Http\JsonResponse;

/**
 * El tablero de completitud de los perfiles (docs/diseno-manual-de-puestos.md,
 * P6, PR 15): «Perfiles» en Puestos y cargos. Con `gestionar-puestos`, en la
 * ruta: es quien completa los perfiles.
 */
final class CompletitudDePerfilesController extends Controller
{
    public function __invoke(CompletitudDePerfilesService $completitud): JsonResponse
    {
        return ApiResponse::ok($completitud->tablero(), 'Completitud de los perfiles.');
    }
}
