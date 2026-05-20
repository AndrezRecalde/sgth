<?php

namespace App\Http\Resources\Geografia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProvinciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
        ];
    }
}
