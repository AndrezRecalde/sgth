<?php

namespace App\Http\Resources\Viatico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'codigo_comision'          => $this->codigo_comision,
            'motivo'                   => $this->motivo,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'fecha_inicio'             => $this->fecha_inicio?->toDateString(),
            'fecha_fin'                => $this->fecha_fin?->toDateString(),
            'documento_autorizacion'   => $this->documento_autorizacion,
            'estado'                   => $this->estado,
            'creado_por'               => $this->creado_por,
            'created_at'               => $this->created_at?->toDateTimeString(),
        ];
    }
}
