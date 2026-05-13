<?php

namespace App\Http\Controllers\Actividades;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Actividades\ActividadLaboral;
use App\Contracts\Actividades\ActividadesServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActividadLaboralController extends Controller
{
    public function __construct(private readonly ActividadesServiceInterface $service)
    {
    }

    public function index(Request $request)
    {
        // En una implementación real, se extrae el servidor_id del usuario autenticado
        $servidorId = $request->user()->servidor_id ?? $request->input('servidor_id');
        
        $query = ActividadLaboral::where('servidor_id', $servidorId);

        if ($request->has('fecha')) {
            $query->whereDate('fecha', $request->input('fecha'));
        }

        return ApiResponse::ok($query->latest('fecha')->get(), 'Actividades del servidor listadas correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'servidor_id' => 'required|exists:servidores,id',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|max:50',
            'producto_entregable' => 'nullable|string|max:255'
        ]);

        $actividad = $this->service->registrarActividad($validated);
        
        return ApiResponse::created($actividad, 'Actividad registrada exitosamente.');
    }

    public function show(int $id)
    {
        $actividad = ActividadLaboral::findOrFail($id);
        return ApiResponse::ok($actividad, 'Detalle de la actividad.');
    }

    public function update(Request $request, int $id)
    {
        $actividad = ActividadLaboral::findOrFail($id);

        $validated = $request->validate([
            'fecha' => 'date',
            'hora_inicio' => 'date_format:H:i',
            'hora_fin' => 'date_format:H:i|after:hora_inicio',
            'descripcion' => 'string',
            'categoria' => 'string|max:50',
            'producto_entregable' => 'nullable|string|max:255'
        ]);

        $actividad->update($validated);
        return ApiResponse::ok($actividad, 'Actividad actualizada correctamente.');
    }

    public function destroy(int $id)
    {
        $actividad = ActividadLaboral::findOrFail($id);
        $actividad->delete();
        return ApiResponse::ok(null, 'Actividad eliminada correctamente.');
    }

    public function porUnidad(Request $request)
    {
        // Solo para Jefes/Directores: Lista las actividades de los servidores que pertenecen a su misma unidad
        // Aquí se asumiría que extraemos el unidad_administrativa_id del usuario autenticado
        $unidadId = $request->input('unidad_administrativa_id');

        $actividades = ActividadLaboral::whereHas('servidor', function ($query) use ($unidadId) {
            $query->where('unidad_administrativa_id', $unidadId);
        })->latest('fecha')->get();

        return ApiResponse::ok($actividades, 'Actividades del equipo listadas correctamente.');
    }

    public function exportarInforme(Request $request)
    {
        $validated = $request->validate([
            'servidor_id' => 'required|exists:servidores,id',
            'mes' => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000'
        ]);

        $informe = $this->service->generarInformeMensual(
            $validated['servidor_id'],
            $validated['mes'],
            $validated['anio']
        );

        return ApiResponse::ok($informe, 'Informe PDF exportado correctamente.');
    }
}