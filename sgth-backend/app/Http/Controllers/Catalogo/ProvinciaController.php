<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\Geografia\ProvinciaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Geografia\Provincia;

class ProvinciaController extends Controller
{
    public function index()
    {
        $provincias = Provincia::orderBy('nombre')->get();
        return ApiResponse::ok(
            ProvinciaResource::collection($provincias),
            'Provincias listadas exitosamente.'
        );
    }
}
