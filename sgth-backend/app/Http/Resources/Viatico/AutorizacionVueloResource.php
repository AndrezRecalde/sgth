<?php
namespace App\Http\Resources\Viatico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutorizacionVueloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'tramo_viatico_id'          => $this->tramo_viatico_id,
            'viatico_id'                => $this->viatico_id,
            'documento_invitacion_ruta' => $this->documento_invitacion_ruta,
            'justificacion'             => $this->justificacion,
            'estado'                    => $this->estado,
            'aprobado_por'              => $this->aprobado_por,
            'observacion_aprobador'     => $this->observacion_aprobador,
            'aprobado_en'               => $this->aprobado_en?->toDateTimeString(),

            // Relación con el viático y servidor
            'viatico' => $this->whenLoaded('viatico', fn() => [
                'id'              => $this->viatico->id,
                'codigo_viatico'  => $this->viatico->codigo_viatico,
                'servidor' => $this->viatico->relationLoaded('servidor')
                    ? [
                        'nombre'   => $this->viatico->servidor?->nombre,
                        'apellido' => $this->viatico->servidor?->apellido,
                        'puesto'   => $this->viatico->servidor?->relationLoaded('puesto')
                            ? [
                                'cargo' => [
                                    'nombre' => $this->viatico->servidor
                                        ?->puesto?->cargo?->nombre,
                                ],
                            ]
                            : null,
                    ]
                    : null,
            ]),

            // Relación con el tramo
            'tramo' => $this->whenLoaded('tramo', fn() => [
                'id'             => $this->tramo->id,
                'orden'          => $this->tramo->orden,
                'origen_tipo'    => $this->tramo->origen_tipo,
                'destino_tipo'   => $this->tramo->destino_tipo,
                'origen_pais'    => $this->tramo->origen_pais,
                'origen_ciudad'  => $this->tramo->origen_ciudad,
                'destino_pais'   => $this->tramo->destino_pais,
                'destino_ciudad' => $this->tramo->destino_ciudad,
                'datetime_salida'  => $this->tramo->datetime_salida,
                'datetime_llegada' => $this->tramo->datetime_llegada,
                'empresa' => $this->tramo->relationLoaded('empresa')
                    ? ['nombre' => $this->tramo->empresa?->nombre]
                    : null,
                'origenProvincia' => $this->tramo->relationLoaded('origenProvincia')
                    ? ['nombre' => $this->tramo->origenProvincia?->nombre]
                    : null,
                'origenCanton' => $this->tramo->relationLoaded('origenCanton')
                    ? ['nombre' => $this->tramo->origenCanton?->nombre]
                    : null,
                'destinoProvincia' => $this->tramo->relationLoaded('destinoProvincia')
                    ? ['nombre' => $this->tramo->destinoProvincia?->nombre]
                    : null,
                'destinoCanton' => $this->tramo->relationLoaded('destinoCanton')
                    ? ['nombre' => $this->tramo->destinoCanton?->nombre]
                    : null,
            ]),
        ];
    }
}
