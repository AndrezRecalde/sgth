<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\DisponibilidadServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DisponibilidadController extends Controller
{
    public function __construct(
        private readonly DisponibilidadServiceInterface $service,
    ) {}

    public function miEstado(Request $request): JsonResponse
    {
        $disponible = $this->service->obtenerEstado(
            $request->user()->id
        );

        return ApiResponse::ok(['disponible' => $disponible]);
    }

    public function alternar(Request $request): JsonResponse
    {
        $disponible = $this->service->alternar(
            $request->user()->id
        );

        $mensaje = $disponible
            ? 'Ahora estás disponible para atención.'
            : 'Ya no estás disponible para atención.';

        return ApiResponse::ok(
            ['disponible' => $disponible], $mensaje
        );
    }
}
