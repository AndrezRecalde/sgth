<?php

namespace App\Mail\Helpdesk;

use App\Models\Helpdesk\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Los avisos de la mesa de ayuda (docs/diseno-subsistema-tic.md, 6; fase 3.5):
 * al técnico cuando le asignan, le escalan o le reabren un ticket; al
 * solicitante cuando TIC le pregunta algo o le resuelve el ticket. Las
 * alertas del SLA van aparte, en `TicketSlaMail`.
 */
class TicketAvisoMail extends Mailable
{
    use Queueable, SerializesModels;

    public const AVISOS = [
        'asignado'  => 'Le asignaron un ticket',
        'reabierto' => 'Reabrieron un ticket suyo',
        'pendiente' => 'TIC necesita su respuesta',
        'resuelto'  => 'Su ticket está resuelto',
    ];

    public function __construct(public readonly Ticket $ticket, public readonly string $aviso) {}

    /** Al técnico, o al solicitante. */
    public function paraTic(): bool
    {
        return in_array($this->aviso, ['asignado', 'reabierto'], true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::AVISOS[$this->aviso] . ": {$this->ticket->codigo_ticket} — Mesa de ayuda de TIC");
    }

    public function content(): Content
    {
        $t = $this->ticket;
        $s = $t->solicitante;
        $base = rtrim((string) config('app.frontend_url'), '/');
        $ultimaPregunta = $this->aviso === 'pendiente'
            ? $t->comentarios()->where('es_interno', false)->latest('id')->value('comentario')
            : null;

        return new Content(
            markdown: 'mail.helpdesk.aviso',
            with: [
                'aviso'       => $this->aviso,
                'titulo'      => self::AVISOS[$this->aviso],
                'folio'       => $t->codigo_ticket,
                'asunto'      => $t->asunto,
                'solicitante' => $s ? trim("{$s->nombre} {$s->apellido}") : '',
                'tecnico'     => $t->tecnico?->nombre_completo,
                'prioridad'   => $t->prioridad->etiqueta(),
                'vence'       => $t->fecha_vencimiento_sla?->format('d/m/Y H:i'),
                'solucion'    => $t->solucion,
                'pregunta'    => $ultimaPregunta,
                'url'         => $this->paraTic() ? "{$base}/tic/tickets/{$t->id}" : "{$base}/portal/mis-tickets/{$t->id}",
            ],
        );
    }
}
