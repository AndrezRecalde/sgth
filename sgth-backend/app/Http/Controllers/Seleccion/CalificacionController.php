<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\CalificacionPostulante;
use App\Models\Seleccion\Postulante;
use App\Services\Seleccion\CalificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalificacionController extends Controller
{
    public function __construct(private readonly CalificacionService $calificaciones) {}

    /**
     * Las calificaciones del candidato con los criterios vigentes, por id de
     * criterio. En un checklist, `opciones` son las marcadas.
     */
    public function obtener(
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $calificaciones = CalificacionPostulante::with(['criterio.opciones', 'opcion', 'opciones'])
            ->where('postulante_id', $postulanteId)
            ->whereHas('criterio', fn ($q) => $q->where('activo', true))
            ->get()
            ->keyBy('criterio_id');

        return ApiResponse::ok([
            'postulante'     => $postulante,
            'calificaciones' => $calificaciones,
        ]);
    }

    /**
     * Una fila por criterio vigente, todos: un total parcial decidía
     * aprobado o reprobado antes de tiempo. El checklist manda sus opciones
     * marcadas en `opcion_ids`.
     */
    public function guardar(
        Request $request,
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::with('convocatoria')
            ->where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $datos = $request->validate([
            'calificaciones'                  => ['required', 'array', 'min:1'],
            'calificaciones.*.criterio_id'    => ['required', 'integer'],
            'calificaciones.*.opcion_id'      => ['nullable', 'integer'],
            'calificaciones.*.opcion_ids'     => ['nullable', 'array'],
            'calificaciones.*.opcion_ids.*'   => ['integer'],
            'calificaciones.*.valor_numerico' => ['nullable', 'numeric', 'min:0'],
            'calificaciones.*.observacion'    => ['nullable', 'string', 'max:1000'],
        ]);

        $evaluacion = $this->calificaciones->guardar(
            $postulante, array_values($datos['calificaciones']), $request->user()->id
        );

        return ApiResponse::ok(
            $evaluacion,
            'Calificación guardada: '.number_format((float) $evaluacion->puntaje_total, 2).' puntos.'
        );
    }
}
