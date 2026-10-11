<?php

namespace App\Mail\Expediente;

use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La liquidación referencial de la terminación de un obrero, para Financiero
 * (fase 4.5), con la hoja de registro interno adjunta. Y, desde la fase 4.5b,
 * lo que se paga por su reintegro cuando el juez declara ineficaz el despido.
 */
class LiquidacionObreroMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{remuneracion: ?float, desde: ?string, anios_servicio: ?float,
     *               rubros: list<array{concepto: string, base_legal: string, detalle: string, monto: ?float}>,
     *               total: ?float, nota: string}  $liquidacion
     */
    public function __construct(
        public readonly MovimientoPersonal $movimiento,
        public readonly array $liquidacion,
        public readonly bool $anulada,
        private readonly string $pdf,
        private readonly string $nombreArchivo,
    ) {
    }

    public function envelope(): Envelope
    {
        $numero = $this->movimiento->codigo_registro;
        $asunto = $this->movimiento->esReintegroPorDespidoIneficaz()
            ? 'Reintegro de obrero: remuneraciones pendientes'
            : 'Liquidación de obrero';

        return new Envelope(
            subject: $this->anulada
                ? "ANULADA — {$asunto} {$numero} — {$this->nombreServidor()}"
                : "{$asunto} para pago {$numero} — {$this->nombreServidor()}",
        );
    }

    public function content(): Content
    {
        $dinero = fn (?float $v) => $v !== null ? '$'.number_format($v, 2, ',', '.') : 'No disponible';

        return new Content(
            markdown: 'mail.expediente.liquidacion-obrero',
            with: [
                'anulada'  => $this->anulada,
                'reintegro' => $this->movimiento->esReintegroPorDespidoIneficaz(),
                'orden'    => $this->movimiento->ordenJudicial(),
                'causa'    => $this->movimiento->causa_judicial_numero,
                'unidadJudicial' => $this->movimiento->unidad_judicial,
                'numero'   => $this->movimiento->codigo_registro,
                'servidor' => $this->nombreServidor(),
                'cedula'   => $this->movimiento->servidor?->cedula,
                'causal'   => $this->movimiento->subtipoEfectivo()?->etiqueta(),
                'rige'     => $this->movimiento->fecha_efectiva?->format('d/m/Y'),
                'rubros'   => array_map(fn (array $r) => [...$r, 'monto' => $dinero($r['monto'])], $this->liquidacion['rubros']),
                'total'    => $dinero($this->liquidacion['total']),
                'nota'     => $this->liquidacion['nota'],
                'motivo'   => $this->movimiento->motivo_anulacion,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nombreArchivo)->withMime('application/pdf'),
        ];
    }

    private function nombreServidor(): string
    {
        $s = $this->movimiento->servidor;

        return trim(($s?->apellido ?? '').' '.($s?->nombre ?? '')) ?: 'Obrero';
    }
}
