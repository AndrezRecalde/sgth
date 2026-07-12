<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\CalificacionPostulante;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\OpcionCriterio;
use App\Models\Seleccion\Postulante;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CalificacionController extends Controller
{
    public function obtener(
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $calificaciones = CalificacionPostulante::with([
            'criterio.opciones', 'opcion',
        ])
            ->where('postulante_id', $postulanteId)
            ->get()
            ->keyBy('criterio_id');

        return ApiResponse::ok([
            'postulante'     => $postulante,
            'calificaciones' => $calificaciones,
        ]);
    }

    public function guardar(
        Request $request,
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $request->validate([
            'calificaciones'                    => ['required', 'array'],
            'calificaciones.*.criterio_id'      => ['required', 'integer', 'exists:seleccion_criterios,id'],
            'calificaciones.*.opcion_id'        => ['nullable', 'integer', 'exists:seleccion_opciones,id'],
            'calificaciones.*.valor_numerico'   => ['nullable', 'numeric', 'min:0'],
            'calificaciones.*.observacion'      => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $postulante) {
            $totalMeritos   = 0;
            $totalOposicion = 0;

            foreach ($request->input('calificaciones') as $cal) {
                $criterio = CriterioEvaluacion::findOrFail(
                    $cal['criterio_id']
                );

                $puntajeObtenido = 0;

                if ($criterio->tipo_input === 'numero') {
                    $valor = min(
                        (float)($cal['valor_numerico'] ?? 0),
                        (float)$criterio->puntaje_maximo
                    );
                    $puntajeObtenido = $valor;
                } elseif (in_array($criterio->tipo_input, ['radio', 'checklist'])) {
                    if (!empty($cal['opcion_id'])) {
                        $opcion = OpcionCriterio::find($cal['opcion_id']);
                        $puntajeObtenido = min(
                            (float)($opcion?->puntaje ?? 0),
                            (float)$criterio->puntaje_maximo
                        );
                    }
                }

                CalificacionPostulante::updateOrCreate(
                    [
                        'postulante_id' => $postulante->id,
                        'criterio_id'   => $cal['criterio_id'],
                    ],
                    [
                        'opcion_id'       => $cal['opcion_id'] ?? null,
                        'valor_numerico'  => $cal['valor_numerico'] ?? null,
                        'puntaje_obtenido'=> $puntajeObtenido,
                        'observacion'     => $cal['observacion'] ?? null,
                        'registrado_por'  => request()->user()->id,
                    ]
                );

                if ($criterio->seccion === 'meritos') {
                    $totalMeritos += $puntajeObtenido;
                } else {
                    $totalOposicion += $puntajeObtenido;
                }
            }

            $total = $totalMeritos + $totalOposicion;

            \App\Models\Seleccion\EvaluacionSeleccion::updateOrCreate(
                ['postulante_id' => $postulante->id],
                [
                    'puntaje_meritos'   => $totalMeritos,
                    'puntaje_oposicion' => $totalOposicion,
                    'puntaje_total'     => $total,
                    'evaluador_id'      => request()->user()->id,
                    'updated_by'        => request()->user()->id,
                ]
            );

            $postulante->update([
                'estado' => $total >= 70 ? 'aprobado' : 'reprobado',
            ]);
        });

        return ApiResponse::ok([], 'Calificación guardada correctamente.');
    }
}
