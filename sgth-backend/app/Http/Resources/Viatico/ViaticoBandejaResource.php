<?php

namespace App\Http\Resources\Viatico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de la bandeja de Financiero: lo justo para decidir si abrir el
 * viático, sin el itinerario ni la liquidación.
 *
 * @mixin \App\Models\Viatico\Viatico
 */
class ViaticoBandejaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'codigo_viatico'     => $this->codigo_viatico,
            'estado'             => $this->estado,
            'zona'               => $this->zona,
            'modalidad_anticipo' => $this->modalidad_anticipo,
            'datetime_salida'    => $this->datetime_salida,
            'datetime_llegada'   => $this->datetime_llegada,
            'noches'             => $this->noches,
            'monto_calculado'    => $this->monto_calculado,
            'monto_anticipo'     => $this->monto_anticipo,
            'servidor_id'        => $this->servidor_id,
            'servidor'           => $this->whenLoaded('servidor', fn () => [
                'id'       => $this->servidor->id,
                // Los dos nombres y los dos apellidos, como en la solicitud
                // en PDF: en una institución se repiten los primeros.
                'nombre'   => $this->servidor->nombre,
                'segundo_nombre'   => $this->servidor->segundo_nombre,
                'apellido' => $this->servidor->apellido,
                'segundo_apellido' => $this->servidor->segundo_apellido,
                'cedula'   => $this->servidor->cedula,
                'unidad'   => $this->servidor->unidadAdministrativa?->nombre,
            ]),
            // Solo en «por liquidar»: fecha límite, días hábiles que quedan y
            // si ya venció.
            'plazo'              => $this->resource->getAttribute('plazo'),
        ];
    }
}
