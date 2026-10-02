<?php

namespace App\Http\Resources\Sso;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campo por campo y no `parent::toArray()`: Scramble solo infiere la forma de
 * un recurso cuando el arreglo es literal, y si no la infiere el tipo llega al
 * frontend como `unknown[]`. Si agregas una columna a `accidentes_trabajo`,
 * agrégala también aquí.
 *
 * @mixin \App\Models\Sso\AccidenteTrabajo
 */
class AccidenteTrabajoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'servidor_id'              => $this->servidor_id,
            'tipo_evento'              => $this->tipo_evento,
            'fecha_accidente'          => $this->fecha_accidente,
            'hora_accidente'           => $this->hora_accidente,
            'lugar_accidente'          => $this->lugar_accidente,
            'descripcion_hechos'       => $this->descripcion_hechos,
            'gravedad'                 => $this->gravedad,
            'requirio_atencion_medica' => $this->requirio_atencion_medica,
            'dias_reposo_medico'       => $this->dias_reposo_medico,
            'causa_raiz'               => $this->causa_raiz,
            'medidas_correctivas'      => $this->medidas_correctivas,
            'estado'                   => $this->estado,
            'investigado_por'          => $this->investigado_por,

            // Las dos relaciones que el listado ya trae cargadas. Sin
            // declararlas, pasar el `index` por este recurso habría dejado la
            // columna Servidor con el id en vez del nombre.
            'servidor' => $this->whenLoaded('servidor', fn () => [
                'id'       => $this->servidor->id,
                'cedula'   => $this->servidor->cedula,
                'nombre'   => $this->servidor->nombre,
                'apellido' => $this->servidor->apellido,
            ]),

            'investigador' => $this->whenLoaded('investigador', fn () => [
                'id'              => $this->investigador->id,
                'usuario_ti'      => $this->investigador->usuario_ti,
                'nombre_completo' => $this->investigador->nombre_completo,
            ]),

            'created_by'               => $this->created_by,
            'updated_by'               => $this->updated_by,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'deleted_at'               => $this->deleted_at,
        ];
    }
}
