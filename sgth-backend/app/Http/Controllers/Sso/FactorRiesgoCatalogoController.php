<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\CategoriaFactorRiesgo;
use App\Models\Sso\FactorRiesgoCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class FactorRiesgoCatalogoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', new Enum(CategoriaFactorRiesgo::class)],
            'solo_activos' => ['nullable', 'boolean'],
        ]);

        $factores = FactorRiesgoCatalogo::query()
            ->when(
                $request->filled('search'),
                fn($q) => $q->where('nombre', 'ilike', "%{$request->string('search')->value()}%")
            )
            ->when(
                $request->filled('categoria'),
                fn($q) => $q->where('categoria', $request->string('categoria')->value())
            )
            // Antes era `->where('activo', true)` sin excepción: `update` permitía
            // desactivar un factor y ningún listado volvía a mostrarlo, así que
            // reactivarlo era imposible. Sigue siendo el comportamiento por
            // defecto, pero ahora se puede pedir el catálogo completo.
            ->when(
                $request->boolean('solo_activos', true),
                fn($q) => $q->where('activo', true)
            )
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get();

        return ApiResponse::ok($factores, 'Factores de riesgo obtenidos exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'categoria' => ['required', new Enum(CategoriaFactorRiesgo::class)],
        ]);

        $factor = FactorRiesgoCatalogo::create($validated);
        return ApiResponse::created($factor, 'Factor de riesgo registrado exitosamente.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $factor = FactorRiesgoCatalogo::findOrFail($id);

        $validated = $request->validate([
            'nombre'    => ['sometimes', 'required', 'string', 'max:150'],
            'categoria' => ['sometimes', 'required', new Enum(CategoriaFactorRiesgo::class)],
            'activo'    => ['boolean'],
        ]);

        $factor->update($validated);
        return ApiResponse::ok($factor, 'Factor de riesgo actualizado exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $factor = FactorRiesgoCatalogo::findOrFail($id);

        // `withTrashed()`: RiesgoLaboral usa SoftDeletes, así que un riesgo en la
        // papelera no contaba y la guarda dejaba pasar el borrado. La FK es
        // `restrictOnDelete`, de modo que PostgreSQL lo cortaba después con un
        // 500 en vez del 422 que esta comprobación quería dar.
        if ($factor->riesgosLaborales()->withTrashed()->exists()) {
            return ApiResponse::error(
                'No se puede eliminar el factor porque tiene riesgos laborales asociados.',
                null, 422
            );
        }

        $factor->delete();
        return ApiResponse::ok(null, 'Factor de riesgo eliminado exitosamente.');
    }
}
