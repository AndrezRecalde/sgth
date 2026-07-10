<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PuestoActividadController extends Controller
{
    public function index(int $puestoId): JsonResponse
    {
        $puesto = Puesto::findOrFail($puestoId);
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

        Puesto::findOrFail($puestoId);

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

        foreach ($request->input('orden') as $posicion => $id) {
            PuestoActividad::where('puesto_id', $puestoId)
                ->where('id', $id)
                ->update(['orden' => $posicion + 1]);
        }

        return ApiResponse::ok([], 'Orden actualizado.');
    }
}
