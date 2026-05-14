<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\RolPago;

class RolPagoController extends Controller
{
    public function show(int $nominaId, int $servidorId)
    {
        $rolPago = RolPago::with(['nomina', 'servidor', 'nomina.detalles' => function($q) use ($servidorId) {
            $q->where('servidor_id', $servidorId)->with('concepto');
        }])
        ->where('nomina_id', $nominaId)
        ->where('servidor_id', $servidorId)
        ->firstOrFail();

        $this->authorize('view', $rolPago);

        return ApiResponse::ok($rolPago, 'Detalle de Rol de Pago');
    }
}
