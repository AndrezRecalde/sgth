<?php

namespace App\Http\Controllers\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seleccion\CalificarPostulanteRequest;
use App\Http\Requests\Seleccion\DeclararGanadorRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SeleccionController extends Controller
{
    public function __construct(private SeleccionServiceInterface $seleccionService) {}

    public function calificar(int $postulanteId, CalificarPostulanteRequest $request): JsonResponse
    {
        $evaluacion = $this->seleccionService->calificarPostulante(
            $postulanteId,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::ok($evaluacion, 'Calificación del postulante registrada con éxito.');
    }

    public function declararGanador(int $convocatoriaId, DeclararGanadorRequest $request): JsonResponse
    {
        $ganador = $this->seleccionService->declararGanador(
            $convocatoriaId,
            $request->validated('postulante_ganador_id'),
            $request->user()->id
        );

        return ApiResponse::ok($ganador, 'Concurso finalizado. Ganador declarado, onboarding y movimiento de personal (ingreso) generados automáticamente.');
    }
}
