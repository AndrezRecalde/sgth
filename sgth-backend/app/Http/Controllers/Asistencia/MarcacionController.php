<?php

namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Marcacion;
use Illuminate\Http\Request;

class MarcacionController extends Controller
{
    /**
     * Módulo de solo lectura para listar la asistencia transaccional importada del biométrico.
     * No hay métodos store/update/delete para garantizar inmutabilidad.
     */
    public function index(Request $request)
    {
        $marcaciones = Marcacion::with('servidor')
            ->orderBy('fecha_hora', 'desc')
            ->paginate(50);

        return ApiResponse::ok($marcaciones, 'Listado histórico de marcaciones biométricas');
    }
}
