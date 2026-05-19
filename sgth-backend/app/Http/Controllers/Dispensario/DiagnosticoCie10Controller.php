<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\DiagnosticoCie10;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiagnosticoCie10Controller extends Controller
{
    /**
     * Busca diagnósticos CIE-10 para autocompletado.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termino = $request->query('q');

        if (!$termino || strlen($termino) < 2) {
            return ApiResponse::ok([], 'El término de búsqueda debe tener al menos 2 caracteres.');
        }

        $resultados = DiagnosticoCie10::activos()
            ->buscar($termino)
            ->limit(20)
            ->get(['id', 'codigo', 'descripcion', 'categoria']);

        return ApiResponse::ok($resultados);
    }
}
