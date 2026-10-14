<?php

namespace App\Http\Controllers\Tic;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tic\TableroTicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El tablero de Inicio de Tecnologías (fase 4.1): la mesa de ayuda, el
 * inventario y los préstamos, cada bloque con su permiso.
 */
final class TableroTicController extends Controller
{
    public function __construct(private readonly TableroTicService $tablero) {}

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->tablero->para($request->user()), 'Tablero de Tecnologías.');
    }
}
