<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\StoreTransporteViaticoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\TransporteViatico;
use App\Models\Viatico\Viatico;

class TransporteViaticoController extends Controller
{
    public function index(int $viaticoId)
    {
        $transportes = TransporteViatico::where('viatico_id', $viaticoId)->orderBy('fecha_viaje')->get();
        return ApiResponse::ok($transportes, 'Transportes listados exitosamente.');
    }

    public function store(StoreTransporteViaticoRequest $request, int $viaticoId)
    {
        $viatico = Viatico::findOrFail($viaticoId);
        
        $data = $request->validated();
        $data['viatico_id'] = $viaticoId;

        $transporte = TransporteViatico::create($data);

        return ApiResponse::created($transporte, 'Transporte agregado exitosamente.');
    }

    public function update(StoreTransporteViaticoRequest $request, int $viaticoId, int $transporteId)
    {
        $transporte = TransporteViatico::where('viatico_id', $viaticoId)->findOrFail($transporteId);
        $transporte->update($request->validated());

        return ApiResponse::ok($transporte, 'Transporte actualizado exitosamente.');
    }

    public function destroy(int $viaticoId, int $transporteId)
    {
        $transporte = TransporteViatico::where('viatico_id', $viaticoId)->findOrFail($transporteId);
        $transporte->delete();

        return ApiResponse::ok(null, 'Transporte eliminado exitosamente.');
    }
}
