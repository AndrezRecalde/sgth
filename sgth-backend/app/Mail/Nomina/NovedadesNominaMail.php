<?php

namespace App\Mail\Nomina;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Las novedades para nómina de un mes, camino del jefe de Gestión Financiera,
 * que dispone su aplicación en el rol de pagos (fase 7.3a). Financiero paga en
 * su propio sistema y calcula los valores: el SGTH le dice lo que pasó en el
 * mes, con el Excel adjunto.
 */
class NovedadesNominaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{concepto: string, etiqueta: string, cantidad: int}>  $resumen
     */
    public function __construct(
        public readonly string $mes,
        public readonly array $resumen,
        public readonly int $novedades,
        public readonly int $ajustes,
        private readonly string $excel,
        private readonly string $nombreArchivo,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Novedades para nómina — {$this->mes}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.nomina.novedades-nomina',
            with: [
                'mes'       => $this->mes,
                'novedades' => $this->novedades,
                'ajustes'   => $this->ajustes,
                'resumen'   => $this->resumen,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->excel, $this->nombreArchivo)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
