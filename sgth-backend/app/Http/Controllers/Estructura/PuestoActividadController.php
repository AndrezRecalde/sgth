<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las actividades de un puesto: las columnas de la matriz de riesgos del FEMO.
 *
 * Ninguna de estas rutas comprobaba nada: cualquier usuario con sesión —un
 * servidor desde el portal— podía crear, cambiar, reordenar o borrar las
 * actividades de cualquier puesto. Ahora la lectura sigue a
 * `PuestoPolicy::verActividades` y la escritura a `update` del puesto.
 */
final class PuestoActividadController extends Controller
{
    public function index(int $puestoId): JsonResponse
    {
        $puesto = Puesto::findOrFail($puestoId);
        $this->authorize('verActividades', $puesto);

        $actividades = $puesto->actividades()
            ->get();

        return ApiResponse::ok($actividades);
    }

    public function store(
        Request $request,
        int $puestoId
    ): JsonResponse {
        $request->validate([
            'descripcion' => ['required', 'string', 'max:200'],
            'orden'       => ['nullable', 'integer', 'min:1'],
        ]);

        $this->authorize('update', Puesto::findOrFail($puestoId));

        $ultimoOrden = PuestoActividad::where('puesto_id', $puestoId)
            ->max('orden') ?? 0;

        $actividad = PuestoActividad::create([
            'puesto_id'   => $puestoId,
            'descripcion' => $request->string('descripcion')->value(),
            'orden'       => $request->input('orden', $ultimoOrden + 1),
            'activo'      => true,
        ]);

        return ApiResponse::created(
            $actividad, 'Actividad registrada correctamente.'
        );
    }

    public function update(
        Request $request,
        int $puestoId,
        int $actividadId
    ): JsonResponse {
        $request->validate([
            'descripcion' => ['sometimes', 'string', 'max:200'],
            'orden'       => ['sometimes', 'integer', 'min:1'],
            'activo'      => ['sometimes', 'boolean'],
        ]);

        $this->authorize('update', Puesto::findOrFail($puestoId));

        $actividad = PuestoActividad::where('puesto_id', $puestoId)
            ->findOrFail($actividadId);

        $actividad->update($request->only([
            'descripcion', 'orden', 'activo',
        ]));

        return ApiResponse::ok($actividad, 'Actividad actualizada.');
    }

    public function destroy(
        int $puestoId,
        int $actividadId
    ): JsonResponse {
        $this->authorize('update', Puesto::findOrFail($puestoId));

        $actividad = PuestoActividad::where('puesto_id', $puestoId)
            ->findOrFail($actividadId);

        $actividad->delete();

        return ApiResponse::ok([], 'Actividad eliminada.');
    }

    public function reordenar(
        Request $request,
        int $puestoId
    ): JsonResponse {
        $request->validate([
            'orden'    => ['required', 'array'],
            'orden.*'  => ['integer', 'exists:puesto_actividades,id'],
        ]);

        $this->authorize('update', Puesto::findOrFail($puestoId));

        foreach ($request->input('orden') as $posicion => $id) {
            PuestoActividad::where('puesto_id', $puestoId)
                ->where('id', $id)
                ->update(['orden' => $posicion + 1]);
        }

        return ApiResponse::ok([], 'Orden actualizado.');
    }
}
