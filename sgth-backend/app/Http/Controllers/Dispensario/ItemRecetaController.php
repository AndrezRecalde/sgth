<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\RecetaMedica;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ItemRecetaController extends Controller
{
    public function update(
        Request $request,
        int $recetaId,
        int $itemId
    ): JsonResponse {
        $request->validate([
            'cantidad_prescrita' => ['required', 'integer', 'min:1'],
            'dosis'              => ['required', 'string', 'max:100'],
            'frecuencia'         => ['required', 'string', 'max:100'],
            'duracion'           => ['required', 'string', 'max:100'],
            'observaciones'      => ['nullable', 'string', 'max:500'],
        ]);

        $receta = RecetaMedica::findOrFail($recetaId);

        if ($receta->estado !== 'pendiente') {
            throw new ReglaNegocioException(
                'Solo se pueden editar ítems de recetas pendientes.'
            );
        }

        $item = ItemReceta::where('receta_medica_id', $recetaId)
            ->findOrFail($itemId);

        $item->update($request->validated());

        return ApiResponse::ok(
            $item, 'Ítem actualizado correctamente.'
        );
    }

    public function destroy(
        int $recetaId,
        int $itemId
    ): JsonResponse {
        $receta = RecetaMedica::with('items')
            ->findOrFail($recetaId);

        if ($receta->estado !== 'pendiente') {
            throw new ReglaNegocioException(
                'Solo se pueden quitar ítems de recetas pendientes.'
            );
        }

        if ($receta->items->count() <= 1) {
            throw new ReglaNegocioException(
                'La receta debe tener al menos un medicamento.'
            );
        }

        $item = ItemReceta::where('receta_medica_id', $recetaId)
            ->findOrFail($itemId);

        $item->delete();

        return ApiResponse::ok(
            [], 'Ítem eliminado correctamente.'
        );
    }
}
