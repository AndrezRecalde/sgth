<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Handoff\HandoffErp;
use App\Models\User;

class HandoffErpController extends Controller
{
    public function index()
    {
        // En una app real usaríamos Policy. Aquí asumiremos rol de admin.
        if (!auth()->user()->hasRole('admin-uath')) {
            return ApiResponse::noAutorizado();
        }

        $archivos = HandoffErp::with('generadoPor')
            ->orderBy('generado_en', 'desc')
            ->get();

        return ApiResponse::ok($archivos, 'Listado de archivos Handoff ERP');
    }
}
