<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una foto del servidor, vigente o del historial. La imagen no va aquí: se
 * pide aparte, con sesión, por su `id` (`comunicacion/fotografias/{id}/imagen`).
 * Ese archivo no cambia nunca, así que el navegador lo puede guardar.
 *
 * @mixin \App\Models\Comunicacion\FotografiaServidor
 */
class FotografiaServidorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'servidor_id'     => $this->servidor_id,
            'origen'          => $this->origen->value,
            'origen_etiqueta' => $this->origen->etiqueta(),
            'vigente'         => $this->vigente,
            'subida_en'       => $this->created_at?->toIso8601String(),
            'subida_por'      => $this->whenLoaded('subidaPor', fn () => $this->subidaPor ? [
                'id'     => $this->subidaPor->id,
                'nombre' => $this->subidaPor->nombre_completo,
            ] : null),
            'activada_en'     => $this->activada_en?->toIso8601String(),
            'activada_por'    => $this->whenLoaded('activadaPor', fn () => $this->activadaPor ? [
                'id'     => $this->activadaPor->id,
                'nombre' => $this->activadaPor->nombre_completo,
            ] : null),
        ];
    }
}
