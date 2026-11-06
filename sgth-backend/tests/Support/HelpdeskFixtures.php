<?php

use App\Enums\EstadoTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\User;

/*
| La mesa de ayuda centralizada (2026-11-06): el técnico solo toma lo que le
| asignaron. Las pruebas que no tratan del reparto dejan el ticket asignado
| con esto, como lo haría el coordinador, sin pasar por su pantalla.
*/

function asignarTicketA(int $ticketId, User $tecnico): void
{
    Ticket::whereKey($ticketId)->update(['tecnico_id' => $tecnico->id, 'estado' => EstadoTicket::ASIGNADO->value]);
}
