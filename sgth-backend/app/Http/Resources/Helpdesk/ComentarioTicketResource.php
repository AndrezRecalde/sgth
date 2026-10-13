<?php

namespace App\Http\Resources\Helpdesk;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un mensaje de la conversación: quién, cuándo, si es nota interna y su
 * archivo. La ruta del archivo no sale: se baja por su propia ruta, con el
 * permiso de ver el ticket.
 *
 * @mixin \App\Models\Helpdesk\ComentarioTicket
 */
class ComentarioTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ticket_id'      => $this->ticket_id,
            'comentario'     => $this->comentario,
            'es_interno'     => $this->es_interno,
            'autor_id'       => $this->user_id,
            'autor'          => $this->whenLoaded('user', fn () => $this->user?->nombre_completo),
            'tiene_archivo'  => $this->archivo_ruta !== null,
            'archivo_nombre' => $this->archivo_nombre,
            'created_at'     => $this->created_at,
        ];
    }
}
