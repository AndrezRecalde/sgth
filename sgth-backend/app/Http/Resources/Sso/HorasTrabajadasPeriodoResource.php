<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `horas_trabajadas_periodo`,
 * agrégala también aquí.
 *
 * @mixin \App\Models\Sso\HorasTrabajadasPeriodo
 */
class HorasTrabajadasPeriodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'periodo'                  => $this->periodo,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'total_horas'              => $this->total_horas,
            'registrado_por'           => $this->registrado_por,

            // La unidad que el listado ya trae cargada. La tabla la pinta como
            // «Total institucional» cuando no viene, que es lo que significa
            // una fila sin unidad.
            'unidad_administrativa' => $this->whenLoaded('unidadAdministrativa', fn () => [
                'id'     => $this->unidadAdministrativa->id,
                'nombre' => $this->unidadAdministrativa->nombre,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
