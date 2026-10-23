<?php

namespace App\Http\Resources\Autoservicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un examen de una orden, en el Portal: cuál es y si el Dispensario ya tiene
 * su resultado. El resultado en sí no sale de aquí.
 *
 * @mixin \App\Models\Dispensario\ItemOrdenExamen
 */
class MiExamenDeOrdenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nombre'              => $this->examen?->nombre,
            'nota'                => $this->nota,
            'estado'              => $this->estado,
            'motivo_no_realizado' => $this->motivo_no_realizado,
        ];
    }
}
