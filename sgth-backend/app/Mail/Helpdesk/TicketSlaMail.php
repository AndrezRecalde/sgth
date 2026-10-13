<?php

namespace App\Mail\Helpdesk;

use App\Models\Helpdesk\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La alerta del SLA a TIC (fase 3.2): el ticket está por vencer —le queda una
 * cuarta parte del plazo— o ya venció y subió de nivel.
 */
class TicketSlaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Ticket $ticket, public readonly string $aviso) {}

    public function envelope(): Envelope
    {
        $titulo = $this->aviso === 'vencido' ? 'SLA vencido' : 'SLA por vencer';

        return new Envelope(subject: "{$titulo}: {$this->ticket->codigo_ticket} — Mesa de ayuda de TIC");
    }

    public function content(): Content
    {
        $t = $this->ticket;
        $s = $t->solicitante;

        return new Content(
            markdown: 'mail.helpdesk.sla',
            with: [
                'aviso'       => $this->aviso,
                'folio'       => $t->codigo_ticket,
                'asunto'      => $t->asunto,
                'solicitante' => $s ? trim("{$s->nombre} {$s->apellido}") : '',
                'prioridad'   => $t->prioridad->etiqueta(),
                'estado'      => $t->estado->etiqueta(),
                'tecnico'     => $t->tecnico?->nombre_completo,
                'nivel'       => $t->nivel,
                'vence'       => $t->fecha_vencimiento_sla?->format('d/m/Y H:i'),
                'url'         => rtrim((string) config('app.frontend_url'), '/') . "/tic/tickets/{$t->id}",
            ],
        );
    }
}
