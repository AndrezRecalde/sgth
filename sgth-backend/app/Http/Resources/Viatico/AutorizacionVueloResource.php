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
        ];
    }
}
