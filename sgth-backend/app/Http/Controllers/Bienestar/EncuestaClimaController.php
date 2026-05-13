<?php

namespace App\Http\Controllers\Bienestar;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Bienestar\EncuestaClima;
use App\Contracts\Bienestar\BienestarServiceInterface;
use Illuminate\Http\Request;

class EncuestaClimaController extends Controller
{
    public function __construct(private readonly BienestarServiceInterface $service)
    {
    }

    public function index()
    {
        $encuestas = EncuestaClima::latest('fecha_inicio')->get();
        return ApiResponse::ok($encuestas, 'Encuestas de clima listadas correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'anio' => 'required|integer',
            'titulo' => 'required|string|max:150',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'nullable|string|max:50'
        ]);

        $encuesta = EncuestaClima::create($validated);
        
        return ApiResponse::created($encuesta, 'Encuesta de clima creada exitosamente.');
    }

    public function responder(Request $request, int $encuestaId)
    {
        $validated = $request->validate([
            'unidad_administrativa_id' => 'required|integer',
            'liderazgo' => 'required|numeric|min:1|max:5',
            'comunicacion' => 'required|numeric|min:1|max:5',
            'trabajo_en_equipo' => 'required|numeric|min:1|max:5',
            'condiciones_trabajo' => 'required|numeric|min:1|max:5',
            'desarrollo_profesional' => 'required|numeric|min:1|max:5',
            'reconocimiento' => 'required|numeric|min:1|max:5',
            'satisfaccion_general' => 'required|numeric|min:1|max:5'
        ]);

        $datos = $validated;
        $datos['encuesta_id'] = $encuestaId;

        // Limpieza explícita para forzar el anonimato total (Zero Trust)
        if ($request->has('servidor_id') || $request->has('user_id')) {
            unset($datos['servidor_id']);
            unset($datos['user_id']);
        }

        // Delegar al service
        $this->service->registrarRespuestaAnonima($datos);

        return ApiResponse::created([], 'Gracias por su participación. Su respuesta anónima ha sido registrada.');
    }

    public function resultados(Request $request, int $encuestaId)
    {
        $request->validate([
            'unidad_administrativa_id' => 'required|integer'
        ]);

        $unidadId = $request->input('unidad_administrativa_id');

        // Solo retorna promedios agregados, garantizando anonimato
        $resultados = $this->service->obtenerResultadosAgregadosPorUnidad($encuestaId, $unidadId);

        return ApiResponse::ok($resultados, 'Resultados agregados por unidad calculados correctamente.');
    }
}
