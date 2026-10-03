<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\MiJornadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiJornadaController extends Controller
{
    public function __construct(
        private readonly MiJornadaService $miJornada,
    ) {}

    /** Lo de quien pregunta: sus turnos, sus pendientes y su mes. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'perfil' => ['nullable', 'in:clinico,enfermeria'],
        ]);

        return ApiResponse::ok($this->miJornada->resumen($request->user(), $datos['perfil'] ?? null));
    }
}
