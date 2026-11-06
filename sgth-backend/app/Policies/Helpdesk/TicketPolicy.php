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
 * - El técnico (`gestionar-tickets`) ve y atiende los que tiene asignados,
 *   además de los suyos como solicitante. Desde la mesa de ayuda centralizada
 *   (2026-11-06) no toma los que nadie tiene: los reparte el coordinador.
 * - El jefe de TIC y el coordinador de la mesa de ayuda (`ver-tickets-todos`)
 *   ven y atienden todos, reparten con `asignar-ticket` y registran a nombre
 *   de otro con `registrar-ticket-a-nombre`.
 *
 * El alcance del listado sale de `Ticket::scopeVisiblesPara`, con la misma
 * regla. En qué estado se puede hacer cada cosa lo decide el servicio. admin-ti
 * tiene los permisos del jefe de TIC en su rol.
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

    /**
     * Registrar un ticket a nombre de un servidor que llamó o vino (canal TIC):
     * el coordinador y el jefe, no el técnico (2026-11-06).
     */
    public function registrarANombre(User $user): bool
    {
        return $user->can(Permiso::REGISTRAR_TICKET_A_NOMBRE->value);
    }

    /** Confirmar la solución, calificar o reabrir: solo quien lo abrió. */
    public function responderComoSolicitante(User $user, Ticket $ticket): bool
    {
        return $this->esSolicitante($user, $ticket);
    }

    /**
     * Anularlo por duplicado o mal registrado: quien reparte el trabajo, o el
     * propio solicitante mientras nadie lo atiende (el estado lo mira el
     * servicio).
     */
    public function anular(User $user, Ticket $ticket): bool
    {
        return $user->can(Permiso::ASIGNAR_TICKET->value) || $this->esSolicitante($user, $ticket);
    }

    /** Tomarlo, pedir información, resolverlo, escalarlo o vincularle un equipo. */
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

    /** Lo suyo; lo que nadie tiene, solo para quien reparte. */
    private function esDeSuCola(User $user, Ticket $ticket): bool
    {
        if ($ticket->tecnico_id === null) {
            return $user->can(Permiso::ASIGNAR_TICKET->value);
        }

        return (int) $ticket->tecnico_id === (int) $user->id;
    }

    private function esSolicitante(User $user, Ticket $ticket): bool
    {
        return $user->servidor_id !== null
            && (int) $ticket->solicitante_id === (int) $user->servidor_id;
    }
}
