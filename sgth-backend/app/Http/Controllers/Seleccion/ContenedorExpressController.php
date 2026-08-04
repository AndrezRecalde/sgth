<?php

namespace App\Http\Controllers\Seleccion;

use App\Enums\EstadoPostulante;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reclutamiento express: cuatro contenedores permanentes, uno por modalidad de
 * vinculación que no pasa por concurso. A diferencia de una convocatoria
 * formal, no tienen período — el año lo aporta la fecha de inscripción de cada
 * aspirante, y por eso todos los filtros son por año o rango de años.
 */
final class ContenedorExpressController extends Controller
{
    /**
     * Una tarjeta por modalidad con sus conteos. Los filtros de año se aplican
     * a los conteos, no a los contenedores: las cuatro tarjetas siempre están.
     */
    public function resumen(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rangoAnios($request);

        $contenedores = Convocatoria::where('es_contenedor_permanente', true)
            ->orderBy('titulo')
            ->get();

        $resumen = $contenedores->map(function (Convocatoria $c) use ($desde, $hasta) {
            $base = Postulante::where('convocatoria_id', $c->id)
                ->when($desde, fn ($q) => $q->whereYear('fecha_inscripcion', '>=', $desde))
                ->when($hasta, fn ($q) => $q->whereYear('fecha_inscripcion', '<=', $hasta));

            $porEstado = (clone $base)
                ->selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $enEstados = fn (array $estados) => collect($estados)
                ->sum(fn (string $e) => (int) ($porEstado[$e] ?? 0));

            return [
                'convocatoria_id'            => $c->id,
                'codigo'                     => $c->codigo,
                'titulo'                     => $c->titulo,
                'descripcion'                => $c->descripcion,
                'tipo_nombramiento_previsto' => $c->tipo_nombramiento_previsto?->value,
                'total_aspirantes'           => (int) $porEstado->sum(),
                // "Aceptados" = superaron la evaluación, sin importar en qué
                // punto del flujo estén después.
                'aprobados' => $enEstados([
                    EstadoPostulante::APROBADO->value,
                    EstadoPostulante::GANADOR_POTENCIAL->value,
                    EstadoPostulante::SELECCIONADO->value,
                    EstadoPostulante::LISTA_ESPERA->value,
                    EstadoPostulante::INCORPORADO->value,
                ]),
                'incorporados' => $enEstados([EstadoPostulante::INCORPORADO->value]),
                'pendientes'   => $enEstados([
                    EstadoPostulante::INSCRITO->value,
                    EstadoPostulante::EN_EVALUACION->value,
                ]),
                'reprobados' => $enEstados([
                    EstadoPostulante::REPROBADO->value,
                    EstadoPostulante::DESCALIFICADO->value,
                ]),
            ];
        });

        return ApiResponse::ok([
            'anio_desde'   => $desde,
            'anio_hasta'   => $hasta,
            'contenedores' => $resumen,
        ], 'Resumen de reclutamiento express.');
    }

    /** Aspirantes de una modalidad, filtrables por año o rango de años. */
    public function aspirantes(Request $request, int $convocatoriaId): JsonResponse
    {
        $contenedor = Convocatoria::where('es_contenedor_permanente', true)
            ->findOrFail($convocatoriaId);

        [$desde, $hasta] = $this->rangoAnios($request);

        $query = Postulante::with([
            'puesto.cargo:id,nombre',
            'puesto.unidadAdministrativa:id,nombre',
            'evaluacion',
            'servidor:id,cedula',
        ])
            ->where('convocatoria_id', $contenedor->id)
            ->when($desde, fn ($q) => $q->whereYear('fecha_inscripcion', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereYear('fecha_inscripcion', '<=', $hasta))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->orderByDesc('fecha_inscripcion');

        return ApiResponse::ok(
            $query->paginate($request->integer('per_page', 20)),
            "Aspirantes de {$contenedor->titulo}."
        );
    }

    /**
     * Años con inscripciones, para poblar el filtro sin inventar un rango
     * arbitrario.
     */
    public function aniosDisponibles(): JsonResponse
    {
        $anios = Postulante::whereHas(
            'convocatoria',
            fn ($q) => $q->where('es_contenedor_permanente', true)
        )
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha_inscripcion)::int as anio')
            ->orderByDesc('anio')
            ->pluck('anio');

        return ApiResponse::ok($anios, 'Años con aspirantes registrados.');
    }

    /**
     * Un solo año (anio) o un rango (anio_desde/anio_hasta). Sin parámetros no
     * se filtra: se ven todos los años.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function rangoAnios(Request $request): array
    {
        if ($request->filled('anio')) {
            $anio = $request->integer('anio');

            return [$anio, $anio];
        }

        return [
            $request->filled('anio_desde') ? $request->integer('anio_desde') : null,
            $request->filled('anio_hasta') ? $request->integer('anio_hasta') : null,
        ];
    }
}
