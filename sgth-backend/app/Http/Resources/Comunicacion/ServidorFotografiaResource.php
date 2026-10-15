<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un servidor en la pantalla Fotografías: quién es, dónde trabaja y su foto
 * vigente, si la tiene. Sin datos del expediente que Comunicación no necesita.
 *
 * @mixin \App\Models\Expediente\Servidor
 */
class ServidorFotografiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'cedula'          => $this->cedula,
            'nombre_completo' => $this->nombre_completo,
            'estado'          => $this->estado,
            'unidad'          => $this->unidadAdministrativa ? [
                'id'     => $this->unidadAdministrativa->id,
                'nombre' => $this->unidadAdministrativa->nombre,
            ] : null,
            'cargo'           => $this->puesto?->cargo?->nombre,
            'fotografia'      => $this->fotografiaVigente
                ? new FotografiaServidorResource($this->fotografiaVigente)
                : null,
        ];
    }
}
