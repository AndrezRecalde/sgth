<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\AusenciaTemporalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quiénes están temporalmente ausentes y qué huecos quedan por cubrir. Solo
 * lectura: una ausencia no se crea aquí, nace de una acción de personal de
 * comisión de servicios o licencia sin remuneración.
 */
class AusenciaTemporalController extends Controller
{
    public function __construct(private AusenciaTemporalService $ausencias)
    {
    }

    public function index(Request $request): JsonResponse
    {
        // La regla 'boolean' solo acepta true/false/0/1, no las cadenas
        // "true"/"false" que es como viaja un booleano en un query string.
        // Se normaliza antes de validar para no rechazar con un 422 lo que el
        // cliente envió correctamente.
        if ($request->has('cubiertas')) {
            $request->merge(['cubiertas' => $request->boolean('cubiertas')]);
        }

        $request->validate([
            'fecha'     => ['nullable', 'date'],
            'cubiertas' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::ok(
            $this->ausencias->listar([
                'fecha'     => $request->input('fecha'),
                'cubiertas' => $request->has('cubiertas') ? $request->boolean('cubiertas') : null,
            ]),
            'Ausencias temporales vigentes.'
        );
    }
}
