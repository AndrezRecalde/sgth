<?php

namespace App\Http\Controllers\Comunicacion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Comunicacion\TableroComunicacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El Inicio de Comunicación Social (fase 3.3): las fotos, las credenciales y
 * los logos, cada bloque con su permiso.
 */
final class TableroComunicacionController extends Controller
{
    public function __construct(private readonly TableroComunicacionService $tablero) {}

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->tablero->para($request->user()), 'Inicio de Comunicación Social.');
    }
}
