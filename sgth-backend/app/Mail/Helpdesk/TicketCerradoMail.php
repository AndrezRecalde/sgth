<?php

namespace App\Mail\Helpdesk;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCerradoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $encuesta;

    /**
     * Create a new message instance.
     */
    public function __construct($ticket, $encuesta)
    {
        $this->ticket = $ticket;
        $this->encuesta = $encuesta;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'view.name',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
