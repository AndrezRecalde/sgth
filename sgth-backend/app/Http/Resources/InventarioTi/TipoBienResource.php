<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventarioTi\TipoBien */
class TipoBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre,
            'anios_vida_util' => (int) $this->anios_vida_util,
            'ficha'           => $this->ficha?->value ?? 'general',
            'bienes_count'    => $this->bienes_count ?? null,
            'subtipos'        => $this->whenLoaded('subtipos', fn () => $this->subtipos->map(fn ($s) => [
                'id' => $s->id, 'tipo_bien_id' => $s->tipo_bien_id, 'nombre' => $s->nombre,
            ])->all()),
        ];
    }
}
