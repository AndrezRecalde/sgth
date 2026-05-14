<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\TecnicoDtic;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;

class TecnicoDticController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }

    public function index()
    {
        // En una implementación real, se cargarían las relaciones necesarias
        $tecnicos = TecnicoDtic::with('servidor')->latest()->get();
        return ApiResponse::ok($tecnicos, 'Técnicos DTIC listados correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'servidor_id' => 'required|exists:servidores,id|unique:tecnicos_dtic',
            'area_dtic_id' => 'required|exists:areas_dtic,id',
            'nivel' => 'required|integer|min:1|max:3',
            'estado' => 'nullable|string|max:50'
        ]);

        $tecnico = TecnicoDtic::create($validated);
        
        return ApiResponse::created($tecnico, 'Técnico registrado exitosamente.');
    }

    public function update(Request $request, int $id)
    {
        $tecnico = TecnicoDtic::findOrFail($id);

        $validated = $request->validate([
            'area_dtic_id' => 'exists:areas_dtic,id',
            'nivel' => 'integer|min:1|max:3',
            'estado' => 'string|max:50'
        ]);

        $tecnico->update($validated);
        return ApiResponse::ok($tecnico, 'Datos del técnico actualizados.');
    }

    public function destroy(int $id)
    {
        $tecnico = TecnicoDtic::findOrFail($id);
        $tecnico->delete();
        return ApiResponse::ok(null, 'Técnico desactivado exitosamente.');
    }

    public function cargaTrabajo(int $id)
    {
        // Delegamos al servicio el cálculo de tickets asignados y métricas de rendimiento
        $metricas = $this->service->obtenerCargaTrabajoYMetricas($id);

        return ApiResponse::ok($metricas, 'Carga de trabajo y métricas obtenidas correctamente.');
    }
}
