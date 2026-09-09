<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\RecetaMedica;
use App\Enums\EstadoReceta;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ItemRecetaController extends Controller
{
    /**
     * Estados en los que la receta todavía se puede retocar.
     *
     * `externa` entra porque es terminal para la farmacia, no para el médico:
     * que ninguno de los medicamentos se entregue aquí no quiere decir que ya
     * no se pueda corregir la dosis de uno que el paciente aún no ha comprado.
     *
     * @var list<string>
     */
    private const EDITABLES = [
        EstadoReceta::PENDIENTE->value,
        EstadoReceta::EXTERNA->value,
    ];

    /**
     * Si la receta la emitió quien está pidiendo tocarla.
     *
     * La prescripción la firma quien la emitió, y cambiar una dosis o quitar un
     * medicamento es una decisión clínica sobre esa firma. Hasta ahora
     * cualquier médico podía reescribir la receta de cualquier colega para
     * cualquier paciente, mientras que anular —que es cerrar lo que falta por
     * entregar, no decidir el tratamiento— ya exigía ser su autor.
     *
     * A diferencia de anular, aquí la administración del dispensario no entra:
     * el mostrador cierra entregas, no cambia tratamientos.
     */
    private function laEmitio(RecetaMedica $receta, Request $request): bool
    {
        return $receta->consultaMedica?->medico_id === $request->user()->id;
    }

    public function update(
        Request $request,
        int $recetaId,
        int $itemId
    ): JsonResponse {
        // Lo validado sale de aquí: `validated()` solo existe en los
        // FormRequest, y llamarlo sobre el Request corriente reventaba la
        // edición entera con un 500 antes de tocar nada.
        $datos = $request->validate([
            'cantidad_prescrita' => ['required', 'integer', 'min:1'],
            'dosis'              => ['required', 'string', 'max:100'],
            'frecuencia'         => ['required', 'string', 'max:100'],
            'duracion'           => ['required', 'string', 'max:100'],
            'observaciones'      => ['nullable', 'string', 'max:500'],
        ]);

        $receta = RecetaMedica::with('consultaMedica')->findOrFail($recetaId);

        // Antes que el estado: a quien no tenía por qué editarla no se le
        // responde «esta receta ya no se puede editar».
        if (! $this->laEmitio($receta, $request)) {
            return ApiResponse::error(
                'Solo quien emitió la receta puede cambiar sus medicamentos.',
                null,
                403
            );
        }

        if (! in_array($receta->estado, self::EDITABLES, true)) {
            throw new ReglaNegocioException(
                'Solo se pueden editar ítems de recetas pendientes.'
            );
        }

        $item = ItemReceta::where('receta_medica_id', $recetaId)
            ->findOrFail($itemId);

        $item->update($datos);

        return ApiResponse::ok(
            $item, 'Ítem actualizado correctamente.'
        );
    }

    public function destroy(
        Request $request,
        int $recetaId,
        int $itemId
    ): JsonResponse {
        $receta = RecetaMedica::with(['items', 'consultaMedica'])
            ->findOrFail($recetaId);

        if (! $this->laEmitio($receta, $request)) {
            return ApiResponse::error(
                'Solo quien emitió la receta puede quitar sus medicamentos.',
                null,
                403
            );
        }

        if (! in_array($receta->estado, self::EDITABLES, true)) {
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

        // Quitar el último medicamento del catálogo deja una receta que la
        // farmacia ya no puede tocar. Sin esto se quedaría «pendiente» en la
        // cola del mostrador esperando una entrega que nadie puede hacer.
        $quedaAlgoQueEntregar = $receta->items
            ->where('id', '!=', $item->id)
            ->reject->esExterno()
            ->isNotEmpty();

        if (! $quedaAlgoQueEntregar) {
            $receta->update(['estado' => EstadoReceta::EXTERNA->value]);
        }

        return ApiResponse::ok(
            [], 'Ítem eliminado correctamente.'
        );
    }
}
