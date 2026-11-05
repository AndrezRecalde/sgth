<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\GrupoOcupacional;
use App\Services\Estructura\EscalaRemuneracionService;
use Illuminate\Http\JsonResponse;

class GrupoOcupacionalController extends Controller
{
    /**
     * Los grupos ocupacionales activos, los dos regímenes. La pantalla filtra
     * en el navegador; el filtro por `regimen` que había aquí no lo mandaba
     * nadie.
     */
    public function index(EscalaRemuneracionService $escalas): JsonResponse
    {
        $grupos = GrupoOcupacional::query()
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
