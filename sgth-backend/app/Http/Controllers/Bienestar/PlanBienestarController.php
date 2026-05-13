<?php

namespace App\Http\Controllers\Bienestar;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Bienestar\PlanBienestar;
use Illuminate\Http\Request;

class PlanBienestarController extends Controller
{
    public function index()
    {
        $planes = PlanBienestar::latest('anio')->get();
        return ApiResponse::ok($planes, 'Planes de bienestar listados correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'anio' => 'required|integer|unique:planes_bienestar',
            'presupuesto' => 'required|numeric|min:0',
            'estado' => 'nullable|string|max:50'
        ]);

        $plan = PlanBienestar::create($validated);
        
        return ApiResponse::created($plan, 'Plan de bienestar creado exitosamente.');
    }

    public function show(int $id)
    {
        // Carga el plan con sus actividades relacionadas
        $plan = PlanBienestar::with('actividades')->findOrFail($id);
        return ApiResponse::ok($plan, 'Detalle del plan de bienestar.');
    }
}
