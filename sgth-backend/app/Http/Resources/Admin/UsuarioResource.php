<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nombre_completo' => $this->nombre_completo,
            'email'           => $this->email,
            'usuario_ti'      => $this->usuario_ti,
            'activo'          => $this->activo,
            'primer_login'    => $this->primer_login,
            'servidor_id'     => $this->servidor_id,
            'roles'           => $this->roles->pluck('name'),
            'servidor'        => $this->whenLoaded('servidor', fn() => [
                'id'     => $this->servidor?->id,
                'cedula' => $this->servidor?->cedula,
                'nombre' => trim(
                    ($this->servidor?->nombre   ?? '') . ' ' .
                    ($this->servidor?->apellido ?? '')
                ),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
