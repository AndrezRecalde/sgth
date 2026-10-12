<?php

namespace App\Http\Controllers\Nomina;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Handoff\HandoffErp;
use App\Models\User;

class HandoffErpController extends Controller
{
    public function index()
    {
        // Con el permiso de ver la nómina, no con el rol (fase 7.3c).
        if (! auth()->user()->can(Permiso::VER_NOMINA_TODAS->value)) {
            return ApiResponse::noAutorizado();
        }

        $archivos = HandoffErp::with('generadoPor')
            ->orderBy('generado_en', 'desc')
            ->get();

        return ApiResponse::ok($archivos, 'Listado de archivos Handoff ERP');
    }
}
