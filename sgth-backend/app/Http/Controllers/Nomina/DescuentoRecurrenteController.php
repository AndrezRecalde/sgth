<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nomina\StoreDescuentoRecurrenteRequest;
use App\Http\Requests\Nomina\UpdateDescuentoRecurrenteRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\DescuentoRecurrente;

class DescuentoRecurrenteController extends Controller
{
    public function index()
    {
        $descuentos = DescuentoRecurrente::with(['servidor', 'concepto'])->get();
        return ApiResponse::ok($descuentos, 'Listado de descuentos recurrentes');
    }

    public function store(StoreDescuentoRecurrenteRequest $request)
    {
        $data = $request->validated();
        $data['registrado_por'] = $request->user()->id;

        $descuento = DescuentoRecurrente::create($data);

        return ApiResponse::created($descuento, 'Descuento recurrente creado exitosamente');
    }

    public function show(int $id)
    {
        $descuento = DescuentoRecurrente::with(['servidor', 'concepto', 'registradoPor'])->findOrFail($id);
        return ApiResponse::ok($descuento, 'Detalle de descuento recurrente');
    }

    public function update(UpdateDescuentoRecurrenteRequest $request, int $id)
    {
        $descuento = DescuentoRecurrente::findOrFail($id);
        $descuento->update($request->validated());

        return ApiResponse::ok($descuento, 'Descuento recurrente actualizado exitosamente');
    }

    public function destroy(int $id)
    {
        $descuento = DescuentoRecurrente::findOrFail($id);
        $descuento->delete();

        return ApiResponse::ok(null, 'Descuento recurrente eliminado exitosamente');
    }
}
