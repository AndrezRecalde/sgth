<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El organigrama tal como se publica hacia afuera: la estructura y nada más.
 *
 * Esta respuesta la sirve una ruta sin autenticación, así que la lista de
 * campos es la frontera de lo que la institución hace público. Deliberadamente
 * no incluye ocupantes, subrogaciones, conteos de puestos ni los anclajes de
 * firma, que son información de gestión interna.
 */
class UnidadOrganigramaPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'codigo'      => $this->codigo,
            'nombre'      => $this->nombre,
            'acronimo'    => $this->acronimo,
            'descripcion' => $this->descripcion,
            'nivel'       => $this->nivel,
            'tipo_unidad' => $this->whenLoaded('tipoUnidad', fn () => [
                'id'          => $this->tipoUnidad->id,
                'acronimo'    => $this->tipoUnidad->acronimo,
                'descripcion' => $this->tipoUnidad->descripcion,
            ]),
            'hijos' => UnidadOrganigramaPublicaResource::collection(
                $this->whenLoaded('hijos')
            ),
        ];
    }
}
