<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Expediente\AnotacionAccionPersonal
 */
class AnotacionAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'tipo'           => $this->tipo,
            'etiqueta'       => $this->tipo->etiqueta(),
            'texto'          => $this->texto,
            // El nombre del servidor detrás del usuario, como en el resto del
            // expediente; null si la anotó el sistema o una migración.
            'registrado_por' => $this->whenLoaded(
                'registradoPor',
                fn () => $this->registradoPor?->nombre_completo,
            ),
            'created_at'     => $this->created_at,
        ];
    }
}
