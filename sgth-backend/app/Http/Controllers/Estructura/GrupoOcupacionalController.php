<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\GrupoOcupacional;
use App\Services\Estructura\EscalaRemuneracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrupoOcupacionalController extends Controller
{
    /**
     * Lista todos los grupos ocupacionales.
     * Opcionalmente filtra por régimen laboral.
     */
    public function index(Request $request, EscalaRemuneracionService $escalas): JsonResponse
    {
        $grupos = GrupoOcupacional::query()
            ->when(
                $request->filled('regimen'),
                fn($q) => $q->where('regimen', $request->regimen)
            )
            ->where('activo', true)
            ->orderByRaw("CASE WHEN regimen = 'codigo_trabajo' THEN 1 ELSE 0 END")
            ->orderByDesc('rmu')
            ->get([
                'id',
                'grado_codigo',
                'grado_numerico',
                'grupo',
                'denominacion_generica',
                'rmu',
                'regimen',
                'nivel_complejidad',
                'rol_puesto',
                'activo',
            ])
            // Si sube con la nueva escala (fase 7.3b): la LOSEP, sin las
            // autoridades electas ni los obreros.
            ->map(fn (GrupoOcupacional $g) => [...$g->toArray(), 'admite_escala' => $escalas->impedimentoDelGrado($g) === null]);

        return ApiResponse::ok($grupos, 'Grupos ocupacionales.');
    }
}
