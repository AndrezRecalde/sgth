<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\AreaDtic;
use Illuminate\Http\Request;

class AreaDticController extends Controller
{
    public function index()
    {
        $areas = AreaDtic::latest()->get();
        return ApiResponse::ok($areas, 'Áreas DTIC listadas correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string'
        ]);

        $area = AreaDtic::create($validated);
        
        return ApiResponse::created($area, 'Área DTIC creada exitosamente.');
    }

    /** El `apiResource` declaraba esta ruta y el método no existía: 500 seguro. */
    public function show(int $id)
    {
        $registro = AreaDtic::findOrFail($id);

        return ApiResponse::ok($registro, 'Área DTIC obtenida correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $area = AreaDtic::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'string|max:150',
            'descripcion' => 'nullable|string'
        ]);

        $area->update($validated);
        return ApiResponse::ok($area, 'Área DTIC actualizada exitosamente.');
    }

    public function destroy(int $id)
    {
        $area = AreaDtic::findOrFail($id);
        $area->delete();
        return ApiResponse::ok(null, 'Área DTIC eliminada exitosamente.');
    }
}
