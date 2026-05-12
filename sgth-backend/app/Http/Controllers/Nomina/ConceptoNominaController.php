<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\ConceptoNomina;

class ConceptoNominaController extends Controller
{
    public function index()
    {
        // Administrado solo por uath en app real
        if (!auth()->user()->hasRole('admin-uath')) {
            return ApiResponse::noAutorizado();
        }

        $conceptos = ConceptoNomina::all();

        return ApiResponse::ok($conceptos, 'Listado de conceptos de nómina');
    }
}
