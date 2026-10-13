<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventarioTi\SubtipoBien */
class SubtipoBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'tipo_bien_id' => $this->tipo_bien_id,
            'nombre'       => $this->nombre,
            'bienes_count' => $this->bienes_count ?? null,
        ];
    }
}
