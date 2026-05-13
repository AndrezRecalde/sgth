<?php

namespace App\Http\Controllers\Capacitacion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Capacitacion\PlanCapacitacion;
use Illuminate\Http\Request;

class PlanCapacitacionController extends Controller
{
    public function index()
    {
        $planes = PlanCapacitacion::latest('anio')->get();
        return ApiResponse::ok($planes, 'Planes de capacitación listados correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'anio' => 'required|integer|unique:planes_capacitacion',
            'presupuesto_total' => 'required|numeric|min:0',
            'estado' => 'nullable|string|max:50'
        ]);

        $plan = PlanCapacitacion::create($validated);
        return ApiResponse::created($plan, 'Plan de capacitación creado exitosamente.');
    }

    public function show(int $id)
    {
        // Detalle del plan con avance de ejecución (relación con cursos)
        $plan = PlanCapacitacion::with('cursos')->findOrFail($id);
        return ApiResponse::ok($plan, 'Detalle del plan de capacitación.');
    }

    public function update(Request $request, int $id)
    {
        $plan = PlanCapacitacion::findOrFail($id);

        $validated = $request->validate([
            'anio' => 'integer|unique:planes_capacitacion,anio,' . $id,
            'presupuesto_total' => 'numeric|min:0',
            'estado' => 'string|max:50'
        ]);

        $plan->update($validated);
        return ApiResponse::ok($plan, 'Plan de capacitación actualizado exitosamente.');
    }

    public function destroy(int $id)
    {
        $plan = PlanCapacitacion::findOrFail($id);
        $plan->delete();
        return ApiResponse::ok(null, 'Plan de capacitación eliminado exitosamente.');
    }
}
