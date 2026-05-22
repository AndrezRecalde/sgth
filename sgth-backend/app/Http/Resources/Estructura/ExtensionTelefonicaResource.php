<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionTelefonicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'numero_extension'         => $this->numero_extension,
            'responsable'              => $this->responsable,
            'estado'                   => $this->estado,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'unidad_administrativa'    => $this->whenLoaded(
                'unidadAdministrativa',
                fn() => [
                    'id'     => $this->unidadAdministrativa->id,
                    'nombre' => $this->unidadAdministrativa->nombre,
                ]
            ),
        ];
    }
}
