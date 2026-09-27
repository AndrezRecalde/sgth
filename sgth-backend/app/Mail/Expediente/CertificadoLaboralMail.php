<?php

namespace App\Mail\Expediente;

use App\Models\Expediente\EmisionCertificadoLaboral;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El certificado ya emitido, camino del interesado.
 *
 * Lleva el PDF adjunto y no un enlace: quien lo recibe puede ser un ex
 * servidor sin cuenta en el sistema, y un enlace autenticado no le serviría
 * de nada.
 */
class CertificadoLaboralMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EmisionCertificadoLaboral $emision,
        private readonly string $pdf,
        private readonly string $nombreArchivo,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emision->tipo->titulo().' — GAD Provincial de Esmeraldas',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.expediente.certificado-laboral',
            with: [
                'nombre'   => $this->emision->datos['nombre_completo'] ?? '',
                'codigo'   => $this->emision->codigo,
                'venceEl'  => $this->emision->vence_en->format('d/m/Y'),
                'urlVerificacion' => $this->emision->urlVerificacion(),
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
