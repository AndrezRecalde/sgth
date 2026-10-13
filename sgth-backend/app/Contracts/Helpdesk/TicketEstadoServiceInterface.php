<?php

namespace App\Contracts\Helpdesk;

use App\Models\Helpdesk\Ticket;
use App\Models\User;

/** El trámite de un ticket (docs/diseno-subsistema-tic.md, 4.3). */
interface TicketEstadoServiceInterface
{
    /** @param  array{tecnico_id?: ?int, unidad_tic_id?: ?int, prioridad?: ?string}  $datos */
    public function asignar(Ticket $ticket, array $datos): Ticket;

    /** El técnico lo toma: queda en proceso y a su nombre. */
    public function tomar(Ticket $ticket, User $tecnico): Ticket;

    /** Le falta información: queda esperando al usuario, con la pregunta en la conversación. */
    public function pedirInformacion(Ticket $ticket, User $tecnico, string $pregunta): Ticket;

    /** El solicitante respondió: vuelve a quien lo atiende. */
    public function reanudar(Ticket $ticket, User $user): Ticket;

    /** @param  array{solucion: string, registrar_mantenimiento?: bool, estado_equipo?: ?string}  $datos */
    public function resolver(Ticket $ticket, User $tecnico, array $datos): Ticket;

    /** El solicitante confirma la solución y, si quiere, la califica. */
    public function confirmar(Ticket $ticket, ?int $calificacion, ?string $comentario): Ticket;

    /** El solicitante dice que no quedó resuelto. */
    public function reabrir(Ticket $ticket, User $user, string $motivo): Ticket;

    /** A nivel 2 (especialista) o 3 (proveedor), con otro técnico o de vuelta a la cola. */
    public function escalar(Ticket $ticket, int $nivel, string $motivo, ?int $tecnicoId): Ticket;

    public function anular(Ticket $ticket, User $user, string $motivo): Ticket;

    public function vincularBien(Ticket $ticket, ?int $bienId): Ticket;
}
