<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\GrupoOcupacional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrupoOcupacionalController extends Controller
{
    /**
     * Lista todos los grupos ocupacionales.
     * Opcionalmente filtra por régimen laboral.
     */
    public function index(Request $request): JsonResponse
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
            ]);

        return ApiResponse::ok($grupos, 'Grupos ocupacionales.');
    }
}
