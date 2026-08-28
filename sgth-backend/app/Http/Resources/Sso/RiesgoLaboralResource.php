<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `riesgos_laborales`,
 * agrégala también aquí.
 */
class RiesgoLaboralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'puesto_id'           => $this->puesto_id,
            'factor_riesgo_id'    => $this->factor_riesgo_id,
            'descripcion'         => $this->descripcion,
            'medidas_preventivas' => $this->medidas_preventivas,

            // Valoración NTP 330: los niveles de entrada y los tres derivados
            // que el backend calcula a partir de ellos.
            'nivel_deficiencia'   => $this->nivel_deficiencia,
            'nivel_exposicion'    => $this->nivel_exposicion,
            'nivel_consecuencias' => $this->nivel_consecuencias,
            'nivel_probabilidad'  => $this->nivel_probabilidad,
            'nivel_riesgo_valor'  => $this->nivel_riesgo_valor,
            'nivel_intervencion'  => $this->nivel_intervencion,

            'estado'     => $this->estado,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
