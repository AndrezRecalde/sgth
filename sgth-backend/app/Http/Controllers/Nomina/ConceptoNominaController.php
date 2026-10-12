<?php

namespace App\Http\Controllers\Nomina;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\ConceptoNomina;

class ConceptoNominaController extends Controller
{
    public function index()
    {
        // Con el permiso de ver la nómina, no con el rol (fase 7.3c).
        if (! auth()->user()->can(Permiso::VER_NOMINA_TODAS->value)) {
            return ApiResponse::noAutorizado();
        }

        $conceptos = ConceptoNomina::all();

        return ApiResponse::ok($conceptos, 'Listado de conceptos de nómina');
    }
}
