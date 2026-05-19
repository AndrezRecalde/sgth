<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\Triaje;
use App\Models\Dispensario\AgendaMedica;
use App\Http\Requests\Dispensario\StoreTriajeRequest;
use Illuminate\Http\JsonResponse;

class TriajeController extends Controller
{
    public function store(StoreTriajeRequest $request, int $agendaId): JsonResponse
    {
        $agenda = AgendaMedica::findOrFail($agendaId);
        
        $datos = array_merge($request->validated(), ['agenda_medica_id' => $agenda->id]);
        
        $triaje = Triaje::updateOrCreate(
            ['agenda_medica_id' => $agenda->id],
            $datos
        );

        // Opcional: Actualizar estado de agenda si es necesario, e.g. "en_triaje" -> "esperando_medico"
        if ($agenda->estado === 'programada') {
            $agenda->update(['estado' => 'en_sala']);
        }

        return ApiResponse::created($triaje, 'Triaje registrado exitosamente.');
    }

    public function show(int $agendaId): JsonResponse
    {
        $triaje = Triaje::where('agenda_medica_id', $agendaId)->firstOrFail();
        
        return ApiResponse::ok($triaje);
    }
}
