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

    public function confirmarGanador(
        int $convocatoriaId,
        Request $request
    ): JsonResponse {
        $convocatoria = \App\Models\Seleccion\Convocatoria::findOrFail(
            $convocatoriaId
        );

        if ($convocatoria->estado !== \App\Enums\EstadoConvocatoria::EN_EVALUACION_MEDICA) {
            return \App\Http\Responses\ApiResponse::error(
                'La convocatoria debe estar en evaluación médica para confirmar al ganador.',
                null, 422
            );
        }

        $ganador = \App\Models\Seleccion\Postulante::where(
            'convocatoria_id', $convocatoriaId
        )->where(
            'estado', \App\Enums\EstadoPostulante::GANADOR_POTENCIAL->value
        )->first();

        if (!$ganador) {
            return \App\Http\Responses\ApiResponse::error(
                'No se encontró un ganador potencial para esta convocatoria.',
                null, 422
            );
        }

        $convocatoria->update([
            'estado'     => \App\Enums\EstadoConvocatoria::FINALIZADA,
            'updated_by' => $request->user()->id,
        ]);

        $ganador->update([
            'estado' => \App\Enums\EstadoPostulante::SELECCIONADO,
        ]);

        return \App\Http\Responses\ApiResponse::ok(
            $ganador->fresh(),
            'Ganador confirmado. La convocatoria ha sido finalizada.'
        );
    }
}
