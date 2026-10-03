<?php
namespace App\Http\Controllers\Estructura;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El catálogo de cargos institucionales.
 *
 * La lectura queda abierta a cualquier usuario autenticado: alimenta los
 * selectores de Puestos. Crear, cambiar o borrar un cargo pide
 * `gestionar-puestos`, igual que un puesto; antes no pedía nada, y el código
 * CIUO del cargo es el que hereda la ficha médica ocupacional (FEMO).
 */
class CargoController extends Controller
{
    private function exigirGestion(Request $request): ?JsonResponse
    {
        return $request->user()->can(Permiso::GESTIONAR_PUESTOS->value)
            ? null
            : ApiResponse::error('No tiene permiso para gestionar cargos.', null, 403);
    }

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
        if ($denegado = $this->exigirGestion($request)) {
            return $denegado;
        }

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
        if ($denegado = $this->exigirGestion($request)) {
            return $denegado;
        }

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

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denegado = $this->exigirGestion($request)) {
            return $denegado;
        }

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
