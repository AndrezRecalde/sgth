<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Estructura\TipoUnidad;
use App\Http\Responses\ApiResponse;

class TipoUnidadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tipos = TipoUnidad::orderBy('acronimo')->get();
        return ApiResponse::ok($tipos, 'Tipos de unidad recuperados correctamente');
    }
}
