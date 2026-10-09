<?php

namespace App\Http\Resources\Expediente;

use App\Enums\FamiliaAccionPersonal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una familia del catálogo de acciones de personal, en el orden en que se
 * muestra.
 *
 * @mixin \App\Enums\FamiliaAccionPersonal
 * @property FamiliaAccionPersonal $resource
 */
class FamiliaAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo'   => $this->resource,
            'etiqueta' => $this->resource->etiqueta(),
        ];
    }
}
