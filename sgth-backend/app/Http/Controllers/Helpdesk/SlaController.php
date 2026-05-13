<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Sla;
use Illuminate\Http\Request;

class SlaController extends Controller
{
    public function index()
    {
        $slas = Sla::latest()->get();
        return ApiResponse::ok($slas, 'Configuraciones de SLA listadas correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'categoria_id' => 'required|exists:categorias_ticket,id',
            'prioridad' => 'required|string|max:50',
            'tiempo_resolucion_horas' => 'required|numeric|min:0.5',
            'tiempo_respuesta_horas' => 'required|numeric|min:0.1'
        ]);

        $sla = Sla::create($validated);
        
        return ApiResponse::created($sla, 'Configuración de SLA creada exitosamente.');
    }

    public function update(Request $request, int $id)
    {
        $sla = Sla::findOrFail($id);

        $validated = $request->validate([
            'categoria_id' => 'exists:categorias_ticket,id',
            'prioridad' => 'string|max:50',
            'tiempo_resolucion_horas' => 'numeric|min:0.5',
            'tiempo_respuesta_horas' => 'numeric|min:0.1'
        ]);

        $sla->update($validated);
        
        return ApiResponse::ok($sla, 'Configuración de SLA actualizada exitosamente.');
    }

    public function destroy(int $id)
    {
        $sla = Sla::findOrFail($id);
        $sla->delete();
        
        return ApiResponse::ok(null, 'Configuración de SLA eliminada exitosamente.');
    }
}
