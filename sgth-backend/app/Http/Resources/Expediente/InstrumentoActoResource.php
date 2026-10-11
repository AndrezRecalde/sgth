<?php

namespace App\Http\Resources\Expediente;

use App\Enums\InstrumentoActo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un instrumento del catálogo: con qué documento se formaliza un acto (fase
 * 2.7). Lo lee el filtro de la bandeja.
 *
 * @mixin \App\Enums\InstrumentoActo
 * @property InstrumentoActo $resource
 */
class InstrumentoActoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo'   => $this->resource,
            'etiqueta' => $this->resource->etiqueta(),
        ];
    }
}
