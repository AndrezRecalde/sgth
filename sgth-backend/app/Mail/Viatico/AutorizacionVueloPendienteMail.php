<?php

namespace App\Mail\Viatico;

use App\Models\Viatico\AutorizacionVuelo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutorizacionVueloPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AutorizacionVuelo $autorizacion
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Autorización de Vuelo Pendiente - SGTH',
        );
    }

    public function content(): Content
    {
        $transporte = $this->autorizacion->transporte;
        $viatico = $this->autorizacion->viatico;
        $servidor = $viatico->servidor;
        
        $origen = $transporte->ciudadOrigen ? $transporte->ciudadOrigen->nombre : $transporte->pais_origen;
        $destino = $transporte->ciudadDestino ? $transporte->ciudadDestino->nombre : $transporte->pais_destino;

        return new Content(
            view: 'emails.viaticos.autorizacion-vuelo-pendiente',
            with: [
                'nombreServidor' => $servidor->nombre_completo,
                'rutaVuelo'      => "{$origen} - {$destino}",
                'fechaViaje'     => $transporte->fecha_viaje->format('Y-m-d H:i'),
                'codigoViatico'  => $viatico->codigo_viatico ?? 'N/A',
                'enlaceAprobar'  => url("/api/v1/viaticos/vuelos/{$this->autorizacion->id}/aprobar"),
            ]
        );
    }
}
