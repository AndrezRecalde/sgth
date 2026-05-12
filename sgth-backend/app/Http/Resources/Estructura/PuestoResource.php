<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'codigo'                   => $this->codigo,
            'denominacion'             => $this->denominacion,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'grupo_ocupacional'        => $this->grupo_ocupacional,
            'grado_rmu'                => $this->grado_rmu,
            'rmu'                      => $this->rmu,
            'es_jefe'                  => $this->es_jefe,
            'nivel'                    => $this->nivel,
            'estado'                   => $this->estado,
            
            // Relaciones anidadas condicionales
            'unidad_administrativa'    => new UnidadAdministrativaResource($this->whenLoaded('unidadAdministrativa')),
        ];
    }
}
