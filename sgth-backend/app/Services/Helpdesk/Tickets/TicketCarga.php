<?php

namespace App\Services\Helpdesk\Tickets;

use App\Enums\EstadoTicket;
use App\Models\Helpdesk\HistorialEstadoTicket;
use App\Models\Helpdesk\Ticket;

/**
 * Lo que comparten la apertura y el trámite: cómo se carga un ticket para
 * devolverlo y cómo se anota un cambio de estado.
 */
final class TicketCarga
{
    /** Lo que muestran el listado y el detalle. */
    public const LISTADO = [
        'categoria:id,nombre,es_hardware,es_consumibles', 'solicitante:id,nombre,apellido,cedula,unidad_administrativa_id',
        'solicitante.unidadAdministrativa:id,nombre,acronimo', 'unidadTic:id,nombre',
        'tecnico:id,usuario_ti,email,servidor_id', 'tecnico.servidor:id,nombre,apellido',
        'bien:id,codigo_institucional,modelo,tipo_bien_id,marca_id', 'bien.tipo:id,nombre', 'bien.marca:id,nombre',
    ];

    public function cargar(int $id): Ticket
    {
        $usuario = fn (string $r) => ["{$r}:id,usuario_ti,email,servidor_id", "{$r}.servidor:id,nombre,apellido"];

        return Ticket::with([
            ...self::LISTADO,
            ...$usuario('registradoPor'), ...$usuario('resueltoPor'), ...$usuario('anuladoPor'),
            ...$usuario('comentarios.user'), ...$usuario('historial.usuario'),
            'encuesta',
        ])->findOrFail($id);
    }

    public function anotar(Ticket $ticket, ?EstadoTicket $anterior, ?string $detalle = null): void
    {
        HistorialEstadoTicket::create([
            'ticket_id'       => $ticket->id,
            'estado_anterior' => $anterior,
            'estado_nuevo'    => $ticket->estado,
            'user_id'         => auth()->id(),
            'detalle'         => $detalle,
        ]);
    }
}
