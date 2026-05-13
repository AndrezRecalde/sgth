<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipoProteccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
