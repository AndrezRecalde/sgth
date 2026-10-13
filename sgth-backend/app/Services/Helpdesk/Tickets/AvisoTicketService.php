<?php

namespace App\Services\Helpdesk\Tickets;

use App\Mail\Helpdesk\TicketAvisoMail;
use App\Models\Helpdesk\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Los correos del trámite de un ticket (fase 3.5). Al técnico, a su correo
 * de usuario; al solicitante, al institucional o, si no tiene, al personal.
 * Un correo que no sale no deshace el paso: el ticket ya está registrado y se
 * ve en el sistema.
 */
final class AvisoTicketService
{
    public function enviar(Ticket $ticket, string $aviso): bool
    {
        $ticket->loadMissing('solicitante.usuario', 'tecnico.servidor');
        $correo = new TicketAvisoMail($ticket, $aviso);

        $direccion = $correo->paraTic()
            ? $ticket->tecnico?->email
            : ($ticket->solicitante?->usuario?->email ?: $ticket->solicitante?->correo_personal);

        if (! $direccion) {
            return false;
        }

        try {
            Mail::to($direccion)->send($correo);

            return true;
        } catch (\Throwable $e) {
            Log::warning('No salió el aviso del ticket', ['ticket' => $ticket->codigo_ticket, 'aviso' => $aviso, 'motivo' => $e->getMessage()]);

            return false;
        }
    }
}
