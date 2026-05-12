<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class MovimientoPersonalController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        
        $this->authorize('ver', $servidor);

        $movimientos = MovimientoPersonal::with(['unidadOrigen', 'unidadDestino', 'puestoOrigen', 'puestoDestino', 'autorizadoPor'])
            ->where('servidor_id', $servidorId)
            ->orderBy('fecha_efectiva', 'desc')
            ->get();

        return ApiResponse::ok($movimientos, 'Historial inmutable de movimientos');
    }
}
