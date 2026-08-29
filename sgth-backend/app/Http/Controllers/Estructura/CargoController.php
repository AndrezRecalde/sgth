<?php
namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cargos = Cargo::query()
            ->when(
                $request->filled('search'),
                fn($q) => $q->where('nombre', 'ilike', "%{$request->search}%")
            )
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return ApiResponse::ok($cargos, 'Cargos institucionales.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'                 => ['required', 'string', 'max:200'],
            'denominacion_generica'  => ['nullable', 'string', 'max:100'],
            // Código CIUO-08 (adaptación INEC). Se define aquí, en el cargo, y lo
            // heredan las fichas médicas ocupacionales de todos sus puestos.
            'codigo_ciuo'            => ['nullable', 'string', 'max:10', 'regex:/^[0-9]+$/'],
            'mision'                 => ['nullable', 'string'],
        ]);

        $cargo = Cargo::create($validated);
        return ApiResponse::created($cargo, 'Cargo creado.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cargo = Cargo::findOrFail($id);
        $validated = $request->validate([
            'nombre'                 => ['sometimes', 'string', 'max:200'],
            'denominacion_generica'  => ['nullable', 'string', 'max:100'],
            // Código CIUO-08 (adaptación INEC). Se define aquí, en el cargo, y lo
            // heredan las fichas médicas ocupacionales de todos sus puestos.
            'codigo_ciuo'            => ['nullable', 'string', 'max:10', 'regex:/^[0-9]+$/'],
            'mision'                 => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $cargo->update($validated);
        return ApiResponse::ok($cargo, 'Cargo actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $cargo = Cargo::findOrFail($id);

        if ($cargo->puestos()->exists()) {
            return ApiResponse::error(
                'No se puede eliminar el cargo porque tiene puestos asignados.',
                null, 422
            );
        }

        $cargo->delete();
        return ApiResponse::ok(null, 'Cargo eliminado.');
    }
}
