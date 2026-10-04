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
        $ganadores = $this->seleccionService->declararGanadores(
            $convocatoriaId,
            $request->ganadores(),
            $request->user()->id
        );

        $cantidad = $ganadores->count();

        return ApiResponse::ok(
            $ganadores,
            "{$cantidad} candidato(s) enviados al dispensario médico. "
                .'El ingreso de cada uno se genera recién al confirmar su incorporación '
                .'con dictamen de aptitud.'
        );
    }

    /*
    | Sin `confirmarGanador` desde el 2026-10-04 (decisión de TH). Declaraba
    | «ganador oficial» a todo el que estaba en evaluación médica sin mirar el
    | dictamen —también a un no apto— y finalizaba la convocatoria sin crear
    | el expediente ni el ingreso. El concurso formal se cierra ahora al
    | incorporar a su último ganador:
    | SeleccionService::finalizarSiNoQuedanGanadores().
    */
}
