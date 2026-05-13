<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;

class EncuestaSatisfaccionController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }

    public function index()
    {
        $encuestas = EncuestaSatisfaccion::with('ticket.tecnico')->latest()->get();
        return ApiResponse::ok($encuestas, 'Encuestas de satisfacción listadas correctamente.');
    }

    public function show(int $id)
    {
        $encuesta = EncuestaSatisfaccion::with('ticket')->findOrFail($id);
        return ApiResponse::ok($encuesta, 'Detalle de la encuesta de satisfacción.');
    }

    public function resultados(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'tecnico_dtic_id' => 'nullable|integer',
            'area_dtic_id' => 'nullable|integer'
        ]);

        // Delegar al servicio el cálculo estadístico y la agrupación
        $promedios = $this->service->obtenerResultadosEncuestas($validated);

        return ApiResponse::ok($promedios, 'Resultados promediados de satisfacción obtenidos correctamente.');
    }
}
