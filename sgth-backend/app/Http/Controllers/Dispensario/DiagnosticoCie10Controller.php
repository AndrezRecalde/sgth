<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\DiagnosticoCie10;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiagnosticoCie10Controller extends Controller
{
    /** Cuántos códigos se devuelven como mucho por búsqueda. */
    private const MAXIMO = 20;

    /**
     * Busca diagnósticos CIE-10 para autocompletado.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q'));

        if (strlen($termino) < 2) {
            return ApiResponse::ok(
                [],
                'El término de búsqueda debe tener al menos 2 caracteres.'
            );
        }

        $coincidencias = DiagnosticoCie10::activos()->buscar($termino);

        // El tope de 20 se aplicaba en silencio: con 8918 códigos, quien
        // buscaba algo general veía veinte y no tenía forma de saber que había
        // más. El total va en la respuesta para que la pantalla lo diga.
        $total = (clone $coincidencias)->count();

        $resultados = $coincidencias
            ->ordenadoPorParecido($termino)
            ->limit(self::MAXIMO)
            ->get(['id', 'codigo', 'descripcion', 'categoria']);

        return ApiResponse::ok(
            $resultados,
            'Búsqueda de diagnósticos CIE-10.',
            200,
            [
                'total'     => $total,
                'mostrados' => $resultados->count(),
                'hay_mas'   => $total > $resultados->count(),
            ]
        );
    }
}
