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
            'acronimo'        => $this->acronimo,
            'descripcion'     => $this->descripcion,
            'unidad_padre_id' => $this->unidad_padre_id,
            'nivel'           => $this->nivel,
            'estado'          => $this->estado,
            // Anclajes de los firmantes de las Acciones de Personal.
            'es_unidad_talento_humano' => (bool) $this->es_unidad_talento_humano,
            'es_maxima_autoridad'      => (bool) $this->es_maxima_autoridad,
            'tipo_unidad'     => $this->whenLoaded('tipoUnidad', fn() => [
                'id'          => $this->tipoUnidad->id,
                'acronimo'    => $this->tipoUnidad->acronimo,
                'descripcion' => $this->tipoUnidad->descripcion,
            ]),
            'puestos_count'   => $this->whenLoaded('puestos',
                fn() => $this->puestos->count(), 0
            ),
            'hijos'           => UnidadAdministrativaResource::collection(
                $this->whenLoaded('hijos')
            ),
        ];
    }
}
