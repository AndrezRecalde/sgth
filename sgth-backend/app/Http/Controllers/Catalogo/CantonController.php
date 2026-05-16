<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CantonController extends Controller
{
    /**
     * Lista todos los cantones de una provincia ordenados alfabéticamente
     */
    public function porProvincia(int $provinciaId): JsonResponse
    {
        $provincia = Provincia::find($provinciaId);

        if (!$provincia) {
            return ApiResponse::noEncontrado(
                'La provincia especificada no existe.'
            );
        }

        $cantones = Canton::where('provincia_id', $provinciaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return ApiResponse::ok($cantones);
    }
}
