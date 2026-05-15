<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\StoreComisionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Comision;

class ComisionController extends Controller
{
    public function index()
    {
        $comisiones = Comision::with('unidadAdministrativa')->orderByDesc('created_at')->get();
        return ApiResponse::ok($comisiones, 'Comisiones listadas exitosamente.');
    }

    public function store(StoreComisionRequest $request)
    {
        $data = $request->validated();
        $data['estado'] = 'creada';
        $data['creado_por'] = auth()->id() ?? 1; // Ajustar según el sistema de auth activo

        $comision = Comision::create($data);

        return ApiResponse::created($comision, 'Comisión registrada exitosamente.');
    }

    public function show(int $id)
    {
        $comision = Comision::with('unidadAdministrativa', 'viaticos')->findOrFail($id);
        return ApiResponse::ok($comision, 'Detalle de comisión.');
    }
}
