<?php

namespace App\Mail\InventarioTi;

use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Los avisos del préstamo de equipos (docs/diseno-subsistema-tic.md, 5.3 y
 * D17; fase 2.4): al servidor cuando TIC aprueba, rechaza o cancela, cuando
 * entrega y recibe, antes de la hora de devolución, cuando vence y cuando la
 * reserva no se retiró. A TIC, cuando un préstamo vence.
 */
class PrestamoEquipoMail extends Mailable
{
    use Queueable, SerializesModels;

    public const AVISOS = [
        'aprobado'     => 'Su préstamo está aprobado',
        'rechazado'    => 'Su solicitud de préstamo fue rechazada',
        'cancelado'    => 'Su préstamo fue cancelado',
        'entregado'    => 'Retiró equipos en préstamo',
        'devuelto'     => 'Devolución registrada',
        'recordatorio' => 'Recuerde devolver los equipos',
        'vencido'      => 'Su préstamo está vencido',
        'no_retirado'  => 'Su reserva venció sin retirar los equipos',
        'vencido_tic'  => 'Préstamo vencido sin devolver',
    ];

    public function __construct(public readonly PrestamoEquipo $prestamo, public readonly string $aviso) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::AVISOS[$this->aviso] . " ({$this->prestamo->folio}) — TIC, GAD Provincial de Esmeraldas");
    }

    public function content(): Content
    {
        $p = $this->prestamo;
        $s = $p->servidor;
        $base = rtrim((string) config('app.frontend_url'), '/');

        return new Content(
            markdown: 'mail.prestamos.aviso',
            with: [
                'aviso'    => $this->aviso,
                'titulo'   => self::AVISOS[$this->aviso],
                'nombre'   => $s ? trim("{$s->nombre} {$s->apellido}") : '',
                'folio'    => $p->folio,
                'equipos'  => $p->items->map(fn ($i) => trim("{$i->bien?->codigo_institucional} · " . collect([$i->bien?->tipo?->nombre, $i->bien?->marca?->nombre, $i->bien?->modelo])->filter()->implode(' ')))->all(),
                'inicio'   => $p->inicio_previsto->format('d/m/Y H:i'),
                'fin'      => $p->fin_previsto->format('d/m/Y H:i'),
                'entregado' => $p->entregado_en?->format('d/m/Y H:i'),
                'devuelto' => $p->devuelto_en?->format('d/m/Y H:i'),
                'motivo'   => $p->motivo_rechazo ?? $p->motivo_cancelacion,
                'novedades' => $p->items->filter(fn ($i) => $i->novedad)->map(fn ($i) => "{$i->bien?->codigo_institucional}: {$i->novedad}")->values()->all(),
                // El Portal para el servidor; la ficha del préstamo para TIC.
                'url'      => $this->aviso === 'vencido_tic' ? "{$base}/tic/prestamos/{$p->id}" : "{$base}/portal/mis-prestamos",
            ],
        );
    }
}
