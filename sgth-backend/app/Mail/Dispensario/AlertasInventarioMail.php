<?php

namespace App\Mail\Dispensario;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Resumen diario de lo que la farmacia tiene que atender.
 *
 * Es un solo correo con los tres grupos y no uno por medicina: con un
 * inventario mediano, avisar pieza a pieza convierte la alerta en ruido y se
 * deja de leer.
 */
class AlertasInventarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $bajoMinimo,
        public Collection $porCaducar,
        public Collection $caducadas,
        public int $diasAviso,
    ) {
    }

    public function envelope(): Envelope
    {
        // El asunto lleva las cifras: quien recibe el correo decide si abrirlo
        // ahora sin tener que entrar.
        $partes = [];

        if ($this->caducadas->isNotEmpty()) {
            $partes[] = $this->caducadas->count() . ' caducada(s)';
        }

        if ($this->bajoMinimo->isNotEmpty()) {
            $partes[] = $this->bajoMinimo->count() . ' bajo mínimo';
        }

        if ($this->porCaducar->isNotEmpty()) {
            $partes[] = $this->porCaducar->count() . ' por caducar';
        }

        return new Envelope(
            subject: 'Farmacia — ' . implode(', ', $partes),
        );
    }

    public function content(): Content
    {
        // `markdown` y no `view`: los componentes <x-mail::…> de la plantilla
        // viven en el namespace que registra el renderizador de Markdown. Con
        // `view` el Blade se compila sin él y falla con «No hint path defined
        // for [mail]».
        return new Content(
            markdown: 'mail.dispensario.alertas-inventario',
        );
    }
}
