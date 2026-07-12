<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\PlantillaEvaluacion;
use App\Models\Seleccion\PlantillaCriterio;
use App\Models\Seleccion\PlantillaOpcion;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\OpcionCriterio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PlantillaEvaluacionController extends Controller
{
    public function index(): JsonResponse
    {
        $plantillas = PlantillaEvaluacion::withCount('criterios')
            ->orderBy('nombre')
            ->get();

        return ApiResponse::ok($plantillas);
    }

    public function show(int $id): JsonResponse
    {
        $plantilla = PlantillaEvaluacion::with([
            'criterios.opciones',
        ])->findOrFail($id);

        return ApiResponse::ok($plantilla);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'        => ['required', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string'],
            'tipo_contrato' => ['nullable', 'string', 'max:50'],
        ]);

        $plantilla = PlantillaEvaluacion::create([
            ...$datos,
            'activa'     => true,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(
            $plantilla, 'Plantilla creada correctamente.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plantilla = PlantillaEvaluacion::findOrFail($id);

        $datos = $request->validate([
            'nombre'        => ['sometimes', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string'],
            'tipo_contrato' => ['nullable', 'string', 'max:50'],
            'activa'        => ['sometimes', 'boolean'],
        ]);

        $plantilla->update($datos);

        return ApiResponse::ok($plantilla, 'Plantilla actualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $plantilla = PlantillaEvaluacion::findOrFail($id);
        $plantilla->delete();
        return ApiResponse::ok([], 'Plantilla eliminada.');
    }

    public function agregarCriterio(
        Request $request,
        int $plantillaId
    ): JsonResponse {
        PlantillaEvaluacion::findOrFail($plantillaId);

        $datos = $request->validate([
            'seccion'             => ['required', 'in:meritos,oposicion'],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string'],
            'puntaje_maximo'      => ['required', 'numeric', 'min:0.5'],
            'tipo_input'          => ['required', 'in:radio,numero,checklist'],
            'opciones'            => ['nullable', 'array'],
            'opciones.*.etiqueta' => ['required_with:opciones', 'string'],
            'opciones.*.puntaje'  => ['required_with:opciones', 'numeric', 'min:0'],
        ]);

        $ultimo = PlantillaCriterio::where('plantilla_id', $plantillaId)
            ->where('seccion', $datos['seccion'])
            ->max('orden') ?? 0;

        $criterio = PlantillaCriterio::create([
            'plantilla_id'   => $plantillaId,
            'seccion'        => $datos['seccion'],
            'nombre'         => $datos['nombre'],
            'descripcion'    => $datos['descripcion'] ?? null,
            'puntaje_maximo' => $datos['puntaje_maximo'],
            'tipo_input'     => $datos['tipo_input'],
            'orden'          => $ultimo + 1,
        ]);

        foreach ($datos['opciones'] ?? [] as $i => $op) {
            PlantillaOpcion::create([
                'plantilla_criterio_id' => $criterio->id,
                'etiqueta'              => $op['etiqueta'],
                'puntaje'               => $op['puntaje'],
                'orden'                 => $i + 1,
            ]);
        }

        return ApiResponse::created(
            $criterio->load('opciones'),
            'Criterio agregado a la plantilla.'
        );
    }

    public function eliminarCriterio(
        int $plantillaId,
        int $criterioId
    ): JsonResponse {
        $criterio = PlantillaCriterio::where('plantilla_id', $plantillaId)
            ->findOrFail($criterioId);
        $criterio->delete();
        return ApiResponse::ok([], 'Criterio eliminado.');
    }

    public function aplicarAConvocatoria(
        Request $request,
        int $plantillaId,
        int $convocatoriaId
    ): JsonResponse {
        $plantilla = PlantillaEvaluacion::with([
            'criterios.opciones',
        ])->findOrFail($plantillaId);

        DB::transaction(function () use (
            $plantilla, $convocatoriaId
        ) {
            CriterioEvaluacion::where(
                'convocatoria_id', $convocatoriaId
            )->delete();

            foreach ($plantilla->criterios as $pc) {
                $criterio = CriterioEvaluacion::create([
                    'convocatoria_id' => $convocatoriaId,
                    'seccion'         => $pc->seccion,
                    'nombre'          => $pc->nombre,
                    'descripcion'     => $pc->descripcion,
                    'puntaje_maximo'  => $pc->puntaje_maximo,
                    'tipo_input'      => $pc->tipo_input,
                    'orden'           => $pc->orden,
                    'activo'          => true,
                ]);

                foreach ($pc->opciones as $po) {
                    OpcionCriterio::create([
                        'criterio_id' => $criterio->id,
                        'etiqueta'    => $po->etiqueta,
                        'puntaje'     => $po->puntaje,
                        'orden'       => $po->orden,
                    ]);
                }
            }
        });

        return ApiResponse::ok(
            [], 'Plantilla aplicada a la convocatoria correctamente.'
        );
    }
}
