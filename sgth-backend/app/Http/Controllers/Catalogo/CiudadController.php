<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Catalogo\Ciudad;

class CiudadController extends Controller
{
    public function porProvincia(int $provinciaId)
    {
        $ciudades = Ciudad::where('provincia_id', $provinciaId)->orderBy('nombre')->get();
        return ApiResponse::ok($ciudades, 'Ciudades listadas exitosamente.');
    }
}
