<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Catalogo\EntidadFinanciera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntidadFinancieraController extends Controller
{
    public function index(): JsonResponse
    {
        $entidades = EntidadFinanciera::where('estado', true)->get();
        return response()->json($entidades);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'     => 'required|string|max:255',
            'tipo'       => 'required|in:banco,cooperativa,mutualista,otro',
            'codigo_bce' => 'nullable|string|max:50',
            'estado'     => 'boolean',
        ]);

        $entidad = EntidadFinanciera::create($validated);
        return response()->json($entidad, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $entidad = EntidadFinanciera::findOrFail($id);

        $validated = $request->validate([
            'nombre'     => 'sometimes|required|string|max:255',
            'tipo'       => 'sometimes|required|in:banco,cooperativa,mutualista,otro',
            'codigo_bce' => 'nullable|string|max:50',
            'estado'     => 'boolean',
        ]);

        $entidad->update($validated);
        return response()->json($entidad);
    }

    public function destroy(string $id): JsonResponse
    {
        $entidad = EntidadFinanciera::findOrFail($id);
        $entidad->delete();
        return response()->json(null, 204);
    }
}
