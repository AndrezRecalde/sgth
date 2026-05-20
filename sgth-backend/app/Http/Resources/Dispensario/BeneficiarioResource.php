<?php

namespace App\Http\Resources\Dispensario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'servidor_id'      => $this->servidor_id,
            'nombre'           => $this->nombre,
            'apellido'         => $this->apellido,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'genero'           => $this->genero,
            'cedula'           => $this->cedula,
            'tipo_familiar'    => $this->tipo_familiar,
            'estado'           => $this->estado,
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
