<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventarioTi\Ubicacion */
class UbicacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'sede'         => $this->sede,
            'piso'         => $this->piso,
            'nombre'       => $this->nombre,
            'descripcion'  => $this->descripcion,
            'bienes_count' => $this->bienes_count ?? null,
        ];
    }
}
