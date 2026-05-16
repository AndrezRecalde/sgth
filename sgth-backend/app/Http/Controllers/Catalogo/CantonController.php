<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use Illuminate\Http\JsonResponse;

class CantonController extends Controller
{
    /**
     * Lista todos los cantones de una provincia ordenados alfabéticamente
     */
    public function porProvincia(int $provinciaId): JsonResponse
    {
        // Validar que la provincia existe para evitar respuestas vacías falsas
        if (!Provincia::where('id', $provinciaId)->exists()) {
            return response()->json([
                'mensaje' => 'La provincia especificada no existe.'
            ], 404);
        }

        $cantones = Canton::where('provincia_id', $provinciaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return response()->json([
            'data' => $cantones
        ]);
    }
}
