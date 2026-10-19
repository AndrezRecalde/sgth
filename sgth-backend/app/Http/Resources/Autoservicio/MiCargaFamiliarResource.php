<?php

namespace App\Http\Resources\Autoservicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una carga familiar como la ve su servidor en «Mi perfil» (D13): quién es y
 * si está activa. Sin las condiciones de salud ni las observaciones de
 * Talento Humano: las condiciones son datos sensibles y se registran en
 * persona (D12).
 *
 * @mixin \App\Models\Expediente\CargaFamiliar
 */
class MiCargaFamiliarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'cedula'                    => $this->cedula,
            'nombres'                   => $this->nombres,
            'apellidos'                 => $this->apellidos,
            'parentesco'                => $this->parentesco,
            'parentesco_etiqueta'       => $this->parentesco?->etiqueta(),
            'fecha_nacimiento'          => $this->fecha_nacimiento?->toDateString(),
            'genero'                    => $this->genero,
            'estado'                    => $this->estado,
            // Para no ofrecer pedir dos veces lo mismo.
            'tiene_solicitud_pendiente' => (bool) ($this->resource->tiene_solicitud_pendiente ?? false),
        ];
    }
}
