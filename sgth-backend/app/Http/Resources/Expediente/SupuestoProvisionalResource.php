<?php

namespace App\Http\Resources\Expediente;

use App\Enums\SupuestoProvisional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un supuesto del nombramiento provisional (LOSEP 17 b; fase 3.2), para el
 * selector del ingreso.
 *
 * @mixin \App\Enums\SupuestoProvisional
 * @property SupuestoProvisional $resource
 */
class SupuestoProvisionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo'     => $this->resource,
            'etiqueta'   => $this->resource->etiqueta(),
            'base_legal' => $this->resource->baseLegal(),
        ];
    }
}
