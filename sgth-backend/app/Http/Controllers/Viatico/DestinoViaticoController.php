<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\StoreDestinoViaticoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\DestinoViatico;
use App\Models\Viatico\Viatico;

class DestinoViaticoController extends Controller
{
    public function index(int $viaticoId)
    {
        $destinos = DestinoViatico::where('viatico_id', $viaticoId)->orderBy('orden')->get();
        return ApiResponse::ok($destinos, 'Destinos listados exitosamente.');
    }

    public function store(StoreDestinoViaticoRequest $request, int $viaticoId)
    {
        $viatico = Viatico::findOrFail($viaticoId);
        
        $orden = DestinoViatico::where('viatico_id', $viaticoId)->max('orden') + 1;
        
        $data = $request->validated();
        $data['viatico_id'] = $viaticoId;
        $data['orden'] = $orden;

        $destino = DestinoViatico::create($data);

        return ApiResponse::created($destino, 'Destino agregado exitosamente.');
    }

    public function update(StoreDestinoViaticoRequest $request, int $viaticoId, int $destinoId)
    {
        $destino = DestinoViatico::where('viatico_id', $viaticoId)->findOrFail($destinoId);
        $destino->update($request->validated());

        return ApiResponse::ok($destino, 'Destino actualizado exitosamente.');
    }

    public function destroy(int $viaticoId, int $destinoId)
    {
        $destino = DestinoViatico::where('viatico_id', $viaticoId)->findOrFail($destinoId);
        $destino->delete();

        return ApiResponse::ok(null, 'Destino eliminado exitosamente.');
    }
}
