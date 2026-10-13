<?php

namespace App\Policies\Helpdesk;

use App\Enums\Permiso;
use App\Models\Helpdesk\Ticket;
use App\Models\User;

/**
 * Quién ve y quién atiende un ticket (docs/diseno-subsistema-tic.md, 4.4).
 *
 * Hasta ahora el listado devolvía los tickets de todos a cualquier usuario
 * autenticado, el detalle y los comentarios no miraban de quién era el ticket,
 * y cualquier técnico podía mover el ticket de otro.
 *
 * - El servidor ve los suyos (`ver-ticket-propio`).
 * - El técnico (`gestionar-tickets`) ve y atiende los que tiene asignados y los
 *   que nadie tiene todavía, además de los suyos como solicitante.
 * - El jefe de TIC (`ver-tickets-todos`) ve y atiende todos, y reparte con
 *   `asignar-ticket`.
 *
 * El alcance del listado sale de `Ticket::scopeVisiblesPara`, con la misma
 * regla. En qué estado se puede hacer cada cosa lo decide el servicio, y
 * admin-ti se salta esta policy por el `Gate::before`.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permiso::VER_TICKET_PROPIO->value,
            Permiso::GESTIONAR_TICKETS->value,
            Permiso::VER_TICKETS_TODOS->value,
        ]);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->can(Permiso::VER_TICKETS_TODOS->value)) {
            return true;
        }

        if ($user->can(Permiso::GESTIONAR_TICKETS->value) && $this->esDeSuCola($user, $ticket)) {
            return true;
        }

        return $user->can(Permiso::VER_TICKET_PROPIO->value) && $this->esSolicitante($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::CREAR_TICKET->value);
    }

    /** Cambiar el estado, escalarlo, cerrarlo o vincularle un equipo. */
    public function atender(User $user, Ticket $ticket): bool
    {
        if (! $user->can(Permiso::GESTIONAR_TICKETS->value)) {
            return false;
        }

        return $user->can(Permiso::VER_TICKETS_TODOS->value) || $this->esDeSuCola($user, $ticket);
    }

    public function asignar(User $user, Ticket $ticket): bool
    {
        return $user->can(Permiso::ASIGNAR_TICKET->value);
    }

    /** Escribir en la conversación: quien puede leer el ticket. */
    public function comentar(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Leer y escribir notas internas: la conversación entre quienes atienden. */
    public function verNotasInternas(User $user, Ticket $ticket): bool
    {
        return $user->can(Permiso::GESTIONAR_TICKETS->value) && $this->view($user, $ticket);
    }

    private function esDeSuCola(User $user, Ticket $ticket): bool
    {
        return $ticket->tecnico_id === null || (int) $ticket->tecnico_id === (int) $user->id;
    }

    private function esSolicitante(User $user, Ticket $ticket): bool
    {
        return $user->servidor_id !== null
            && (int) $ticket->solicitante_id === (int) $user->servidor_id;
    }
}
