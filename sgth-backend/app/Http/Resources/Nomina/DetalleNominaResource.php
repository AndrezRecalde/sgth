<?php

namespace App\Http\Resources\Nomina;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalleNominaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nomina_id'        => $this->nomina_id,
            'servidor_id'      => $this->servidor_id,
            'concepto_id'      => $this->concepto_id,
            'tipo'             => $this->tipo,
            'monto'            => $this->monto,
            'observacion'      => $this->observacion,
        ];
    }
}
