<?php

namespace App\Http\Controllers\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seleccion\DeclararGanadorRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeleccionController extends Controller
{
    public function __construct(private SeleccionServiceInterface $seleccionService) {}

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

    /**
     * Cubre la vacante que dejó un no apto con el siguiente de la lista de
     * espera, por puntaje.
     */
    public function declararSiguiente(Request $request, int $convocatoriaId): JsonResponse
    {
        $siguiente = $this->seleccionService->declararSiguiente($convocatoriaId, $request->user()->id);

        $nombre = trim("{$siguiente->apellidos} {$siguiente->nombres}");

        return ApiResponse::ok(
            $siguiente,
            "{$nombre} fue enviado al Dispensario Médico para su certificación de ingreso."
        );
    }

    /*
    | Sin `confirmarGanador` desde el 2026-10-04 (decisión de TH). Declaraba
    | «ganador oficial» a todo el que estaba en evaluación médica sin mirar el
    | dictamen —también a un no apto— y finalizaba la convocatoria sin crear
    | el expediente ni el ingreso. El concurso formal se cierra ahora al
    | incorporar a su último ganador:
    | SeleccionService::cerrarConcursoSiCorresponde().
    */
}
