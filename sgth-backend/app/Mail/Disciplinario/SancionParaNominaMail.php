<?php

namespace App\Mail\Disciplinario;

use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La acción de personal de una multa o una suspensión, camino del jefe de
 * Gestión Financiera, que dispone a quién corresponde aplicar el descuento en
 * el rol de pagos (decisión de TH, 2026-10-04). Financiero descuenta en su
 * propio sistema: el SGTH solo le da el acto firmado y la cifra de referencia.
 *
 * Con `$anulada` es el aviso contrario: la acción que ya se le había enviado
 * quedó sin efecto, y si el descuento se aplicó hay que revertirlo.
 */
class SancionParaNominaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{sancion: string, detalle: string, desde: ?string, hasta: ?string,
     *               base: ?float, monto: ?float}  $descuento
     */
    public function __construct(
        public readonly MovimientoPersonal $movimiento,
        public readonly array $descuento,
        public readonly bool $anulada,
        private readonly string $pdf,
        private readonly string $nombreArchivo,
    ) {
    }

    public function envelope(): Envelope
    {
        $numero   = $this->movimiento->codigo_registro;
        $servidor = $this->nombreServidor();

        return new Envelope(
            subject: $this->anulada
                ? "ANULADA — Sanción disciplinaria {$numero} — {$servidor}"
                : "Sanción disciplinaria para nómina {$numero} — {$servidor}",
        );
    }

    public function content(): Content
    {
        $fecha = fn (?string $f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : null;
        $dinero = fn (?float $v) => $v !== null ? '$'.number_format($v, 2, ',', '.') : 'No disponible';

        return new Content(
            markdown: 'mail.disciplinario.sancion-para-nomina',
            with: [
                'anulada'  => $this->anulada,
                'numero'   => $this->movimiento->codigo_registro,
                'servidor' => $this->nombreServidor(),
                'cedula'   => $this->movimiento->servidor?->cedula,
                'sancion'  => $this->descuento['sancion'],
                'detalle'  => $this->descuento['detalle'],
                'desde'    => $fecha($this->descuento['desde']),
                'hasta'    => $fecha($this->descuento['hasta']),
                'base'     => $dinero($this->descuento['base']),
                'monto'    => $dinero($this->descuento['monto']),
                'motivo'   => $this->movimiento->motivo_anulacion,
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

    private function nombreServidor(): string
    {
        $s = $this->movimiento->servidor;

        return trim(collect([$s?->apellido, $s?->segundo_apellido, $s?->nombre, $s?->segundo_nombre])
            ->filter()->implode(' '));
    }
}
