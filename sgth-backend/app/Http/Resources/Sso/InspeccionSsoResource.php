<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `inspecciones_sso`,
 * agrégala también aquí.
 */
class InspeccionSsoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'fecha_inspeccion'         => $this->fecha_inspeccion,
            'tipo_inspeccion'          => $this->tipo_inspeccion,
            'hallazgos'                => $this->hallazgos,
            'recomendaciones'          => $this->recomendaciones,
            'estado'                   => $this->estado,
            'inspector_id'             => $this->inspector_id,
            'created_by'               => $this->created_by,
            'updated_by'               => $this->updated_by,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'deleted_at'               => $this->deleted_at,
        ];
    }
}
