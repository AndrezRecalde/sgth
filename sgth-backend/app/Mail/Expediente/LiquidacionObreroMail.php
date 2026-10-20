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
 * La terminación de un obrero, para que Financiero la liquide (fase 4.5), con
 * la hoja de registro interno adjunta y lo que corresponde según el Código, sin
 * montos (2026-10-20). Y, desde la fase 4.5b, lo que corresponde por su
 * reintegro cuando el juez declara ineficaz el despido.
 */
class LiquidacionObreroMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{tipo: string, remuneracion: ?float, desde: ?string, hasta: ?string, anios_servicio: ?float,
     *               dias_fuera: ?int, rubros: list<array{concepto: string, base_legal: string, detalle: string}>,
     *               nota: string}  $liquidacion
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
            : 'Terminación de obrero';

        return new Envelope(
            subject: $this->anulada
                ? "ANULADA — {$asunto} {$numero} — {$this->nombreServidor()}"
                : "{$asunto} para liquidar {$numero} — {$this->nombreServidor()}",
        );
    }

    public function content(): Content
    {
        $dinero = fn (?float $v) => $v !== null ? '$'.number_format($v, 2, ',', '.') : 'no registrada';
        $fecha = fn (?string $f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : null;
        $l = $this->liquidacion;

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
                'rubros'   => $l['rubros'],
                // Los datos con que Financiero liquida.
                'datos'    => $l['tipo'] === 'reintegro'
                    ? 'Remuneración: '.$dinero($l['remuneracion']).'. '.($l['dias_fuera']
                        ? "{$l['dias_fuera']} días fuera, del {$fecha($l['desde'])} al {$fecha($l['hasta'])}."
                        : 'Sin días fuera.')
                    : 'Remuneración: '.$dinero($l['remuneracion']).'. Servicio desde el '.($fecha($l['desde']) ?? '—')
                        .($l['anios_servicio'] !== null ? ': '.number_format($l['anios_servicio'], 2, ',', '.').' años.' : '.'),
                'nota'     => $l['nota'],
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
