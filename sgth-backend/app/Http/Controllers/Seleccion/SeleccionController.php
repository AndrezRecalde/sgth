<?php

namespace App\Http\Controllers\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seleccion\CalificarPostulanteRequest;
use App\Http\Requests\Seleccion\DeclararGanadorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function confirmarGanador(
        int $convocatoriaId,
        Request $request
    ): JsonResponse {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);

        if ($convocatoria->estado !== EstadoConvocatoria::EN_EVALUACION_MEDICA) {
            return ApiResponse::error(
                'La convocatoria debe estar en evaluación médica para confirmar al ganador.',
                null, 422
            );
        }

        $ganadores = Postulante::where('convocatoria_id', $convocatoriaId)
            ->where('estado', EstadoPostulante::GANADOR_POTENCIAL->value)
            ->get();

        if ($ganadores->isEmpty()) {
            return ApiResponse::error(
                'No se encontró ningún ganador potencial para esta convocatoria.',
                null, 422
            );
        }

        $convocatoria->update([
            'estado'     => EstadoConvocatoria::FINALIZADA,
            'updated_by' => $request->user()->id,
        ]);

        Postulante::whereIn('id', $ganadores->pluck('id'))
            ->update(['estado' => EstadoPostulante::SELECCIONADO->value]);

        $cantidad = $ganadores->count();

        return ApiResponse::ok(
            $ganadores->map->fresh(),
            "{$cantidad} ganador(es) confirmado(s). La convocatoria ha sido finalizada."
        );
    }
}
