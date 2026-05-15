<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\StoreFacturaViaticoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;

class FacturaViaticoController extends Controller
{
    public function index(int $liquidacionId)
    {
        $facturas = FacturaViatico::where('liquidacion_viatico_id', $liquidacionId)->get();
        return ApiResponse::ok($facturas, 'Facturas listadas exitosamente.');
    }

    public function store(StoreFacturaViaticoRequest $request, int $liquidacionId)
    {
        $liquidacion = LiquidacionViatico::findOrFail($liquidacionId);
        
        $data = $request->validated();
        $data['liquidacion_viatico_id'] = $liquidacionId;

        $factura = FacturaViatico::create($data);

        return ApiResponse::created($factura, 'Factura agregada exitosamente.');
    }

    public function destroy(int $liquidacionId, int $facturaId)
    {
        $factura = FacturaViatico::where('liquidacion_viatico_id', $liquidacionId)->findOrFail($facturaId);
        $factura->delete();

        return ApiResponse::ok(null, 'Factura eliminada exitosamente.');
    }
}
