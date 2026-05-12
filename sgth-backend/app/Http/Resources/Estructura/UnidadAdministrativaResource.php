<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnidadAdministrativaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'codigo'          => $this->codigo,
            'nombre'          => $this->nombre,
            'descripcion'     => $this->descripcion,
            'unidad_padre_id' => $this->unidad_padre_id,
            'nivel'           => $this->nivel,
            'estado'          => $this->estado,
            
            // Relaciones anidadas condicionales
            'padre'           => new UnidadAdministrativaResource($this->whenLoaded('padre')),
            'hijos'           => UnidadAdministrativaResource::collection($this->whenLoaded('hijos')),
            'puestos'         => PuestoResource::collection($this->whenLoaded('puestos')),
        ];
    }
}
