<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `epp_entregas`,
 * agrégala también aquí.
 *
 * Era el único de los seis listados del módulo sin recurso: la bitácora
 * devolvía el modelo crudo, así que el tipo generado describía las columnas de
 * la tabla y ninguna de las tres relaciones que la pantalla sí pinta.
 *
 * @mixin \App\Models\Sso\EppEntrega
 */
class EppEntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'servidor_id'          => $this->servidor_id,
            'equipo_proteccion_id' => $this->equipo_proteccion_id,
            'fecha_entrega'        => $this->fecha_entrega,
            'cantidad'             => $this->cantidad,
            'motivo'               => $this->motivo,
            'entregado_por'        => $this->entregado_por,
            'observaciones'        => $this->observaciones,

            // Las tres relaciones que el listado ya trae cargadas.
            'servidor' => $this->whenLoaded('servidor', fn () => [
                'id'       => $this->servidor->id,
                'cedula'   => $this->servidor->cedula,
                'nombre'   => $this->servidor->nombre,
                'apellido' => $this->servidor->apellido,
            ]),

            'equipo_proteccion' => $this->whenLoaded('equipoProteccion', fn () => [
                'id'     => $this->equipoProteccion->id,
                'codigo' => $this->equipoProteccion->codigo,
                'nombre' => $this->equipoProteccion->nombre,
                'tipo'   => $this->equipoProteccion->tipo,
            ]),

            'entregador' => $this->whenLoaded('entregador', fn () => [
                'id'              => $this->entregador->id,
                'usuario_ti'      => $this->entregador->usuario_ti,
                'nombre_completo' => $this->entregador->nombre_completo,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
