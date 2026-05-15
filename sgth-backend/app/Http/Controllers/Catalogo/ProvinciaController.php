<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Catalogo\Provincia;

class ProvinciaController extends Controller
{
    public function index()
    {
        $provincias = Provincia::orderBy('nombre')->get();
        return ApiResponse::ok($provincias, 'Provincias listadas exitosamente.');
    }
}
