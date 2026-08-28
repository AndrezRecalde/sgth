<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `capacitaciones_sso`,
 * agrégala también aquí.
 */
class CapacitacionSsoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'tema'           => $this->tema,
            'fecha'          => $this->fecha,
            'duracion_horas' => $this->duracion_horas,
            'instructor'     => $this->instructor,
            'lugar'          => $this->lugar,
            'estado'         => $this->estado,
            'created_by'     => $this->created_by,
            'updated_by'     => $this->updated_by,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
