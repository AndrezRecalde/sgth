<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Comunicacion\DatosInstitucionales */
class DatosInstitucionalesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nombre_oficial' => $this->nombre_oficial,
            'siglas'         => $this->siglas,
            'nombre_corto'   => $this->nombre_corto,
            'ruc'            => $this->ruc,
            'direccion'      => $this->direccion,
            'telefono'       => $this->telefono,
            'sitio_web'      => $this->sitio_web,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
            'actualizado_por' => $this->whenLoaded('actualizadoPor', fn () => $this->actualizadoPor ? [
                'id'     => $this->actualizadoPor->id,
                'nombre' => $this->actualizadoPor->nombre_completo,
            ] : null),
        ];
    }
}
