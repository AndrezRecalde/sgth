<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\OpcionCriterio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CriterioEvaluacionController extends Controller
{
    public function index(int $convocatoriaId): JsonResponse
    {
        Convocatoria::findOrFail($convocatoriaId);

        $criterios = CriterioEvaluacion::with('opciones')
            ->where('convocatoria_id', $convocatoriaId)
            ->orderBy('seccion')
            ->orderBy('orden')
            ->get();

        return ApiResponse::ok($criterios);
    }

    public function store(
        Request $request,
        int $convocatoriaId
    ): JsonResponse {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);

        $datos = $request->validate([
            'seccion'         => ['required', 'in:meritos,oposicion'],
            'nombre'          => ['required', 'string', 'max:200'],
            'descripcion'     => ['nullable', 'string'],
            'puntaje_maximo'  => ['required', 'numeric', 'min:0.5', 'max:100'],
            'tipo_input'      => ['required', 'in:radio,numero,checklist'],
            'opciones'        => ['nullable', 'array'],
            'opciones.*.etiqueta' => ['required_with:opciones', 'string', 'max:200'],
            'opciones.*.puntaje'  => ['required_with:opciones', 'numeric', 'min:0'],
        ]);

        $ultimoOrden = CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
            ->where('seccion', $datos['seccion'])
            ->max('orden') ?? 0;

        $criterio = CriterioEvaluacion::create([
            'convocatoria_id' => $convocatoriaId,
            'seccion'         => $datos['seccion'],
            'nombre'          => $datos['nombre'],
            'descripcion'     => $datos['descripcion'] ?? null,
            'puntaje_maximo'  => $datos['puntaje_maximo'],
            'tipo_input'      => $datos['tipo_input'],
            'orden'           => $ultimoOrden + 1,
            'activo'          => true,
        ]);

        if (!empty($datos['opciones'])) {
            foreach ($datos['opciones'] as $i => $opcion) {
                OpcionCriterio::create([
                    'criterio_id' => $criterio->id,
                    'etiqueta'    => $opcion['etiqueta'],
                    'puntaje'     => $opcion['puntaje'],
                    'orden'       => $i + 1,
                ]);
            }
        }

        return ApiResponse::created(
            $criterio->load('opciones'),
            'Criterio registrado correctamente.'
        );
    }

    public function update(
        Request $request,
        int $convocatoriaId,
        int $criterioId
    ): JsonResponse {
        $criterio = CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($criterioId);

        $datos = $request->validate([
            'nombre'         => ['sometimes', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string'],
            'puntaje_maximo' => ['sometimes', 'numeric', 'min:0.5'],
            'activo'         => ['sometimes', 'boolean'],
            'opciones'       => ['nullable', 'array'],
            'opciones.*.etiqueta' => ['required_with:opciones', 'string'],
            'opciones.*.puntaje'  => ['required_with:opciones', 'numeric', 'min:0'],
        ]);

        $criterio->update($datos);

        if (array_key_exists('opciones', $datos)) {
            $criterio->opciones()->delete();
            foreach ($datos['opciones'] as $i => $opcion) {
                OpcionCriterio::create([
                    'criterio_id' => $criterio->id,
                    'etiqueta'    => $opcion['etiqueta'],
                    'puntaje'     => $opcion['puntaje'],
                    'orden'       => $i + 1,
                ]);
            }
        }

        return ApiResponse::ok(
            $criterio->load('opciones'),
            'Criterio actualizado.'
        );
    }

    public function destroy(
        int $convocatoriaId,
        int $criterioId
    ): JsonResponse {
        $criterio = CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($criterioId);

        $criterio->delete();
        return ApiResponse::ok([], 'Criterio eliminado.');
    }
}
