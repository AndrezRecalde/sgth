<?php

namespace App\Http\Resources\Comunicados;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un PDF de una publicación. La ruta en disco no sale: se descarga por
 * controlador, que decide quién la ve.
 *
 * @mixin \App\Models\Comunicados\ComunicadoAdjunto
 */
class ComunicadoAdjuntoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'nombre' => $this->nombre_original,
            'tamano' => $this->tamano,
            'orden'  => $this->orden,
        ];
    }
}
