<?php

namespace App\Mail\Expediente;

use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La notificación de una acción de personal a su titular (Reglamento a la
 * LOSEP, Art. 22; diseño, 6.4; TH 26; fase 6.3a).
 *
 * Lleva el documento adjunto y el enlace a «Mis documentos laborales», donde
 * el servidor acusa recibo. El adjunto va aunque tenga cuenta: la notificación
 * no depende de que entre al portal.
 */
class AccionPersonalNotificadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly MovimientoPersonal $movimiento,
        private readonly string $pdf,
        private readonly string $nombreArchivo,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Acción de personal {$this->movimiento->codigo_registro} — GAD Provincial de Esmeraldas",
        );
    }

    public function content(): Content
    {
        $servidor = $this->movimiento->servidor;

        return new Content(
            markdown: 'mail.expediente.accion-notificada',
            with: [
                'nombre'   => trim(($servidor?->nombre ?? '').' '.($servidor?->apellido ?? '')),
                'codigo'   => $this->movimiento->codigo_registro,
                'accion'   => $this->movimiento->etiqueta(),
                'rige'     => $this->movimiento->fecha_efectiva?->format('d/m/Y'),
                // La página del portal: `ROUTES.PORTAL.DOCUMENTOS` en el frontend.
                'urlPortal' => rtrim((string) config('app.frontend_url'), '/').'/portal/documentos',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nombreArchivo)
                ->withMime('application/pdf'),
        ];
    }
}
