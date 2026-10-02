<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `puesto_epp`, agrégala
 * también aquí.
 *
 * El equipo es lo único que la pantalla pinta de cada fila —el resto son
 * ids—, y era justo lo que el tipo generado no declaraba.
 *
 * @mixin \App\Models\Sso\PuestoEpp
 */
class PuestoEppResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'puesto_id'                   => $this->puesto_id,
            'equipo_proteccion_id'        => $this->equipo_proteccion_id,
            'cantidad_requerida'          => $this->cantidad_requerida,
            'frecuencia_reposicion_meses' => $this->frecuencia_reposicion_meses,

            'equipo_proteccion' => $this->whenLoaded('equipoProteccion', fn () => [
                'id'              => $this->equipoProteccion->id,
                'codigo'          => $this->equipoProteccion->codigo,
                'nombre'          => $this->equipoProteccion->nombre,
                'tipo'            => $this->equipoProteccion->tipo,
                'vida_util_meses' => $this->equipoProteccion->vida_util_meses,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
