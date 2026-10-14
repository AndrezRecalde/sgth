<?php

namespace App\Services\Helpdesk\Tickets;

use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Models\Helpdesk\Ticket;
use App\Services\Helpdesk\Sla\CalendarioHabil;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las cifras de un período de la mesa de ayuda (fase 4.1), sobre los tickets
 * que se le pasen: todos, los de un técnico o los que ve un usuario. Las usan
 * el tablero de TIC, el tablero general y la carga de trabajo del técnico, que
 * antes devolvían números fijos.
 *
 * El tiempo de resolución va en horas hábiles y sin la espera del usuario,
 * como el SLA: lo que mide es cuánto tardó TIC.
 */
final class MetricasTickets
{
    public function __construct(private readonly CalendarioHabil $calendario) {}

    /**
     * @param  Builder<Ticket>  $base
     * @return array{abiertos: int, resueltos: int, cumplimiento_sla: ?int, horas_resolucion: ?float, satisfaccion: ?float, encuestas: int}
     */
    public function delPeriodo(Builder $base, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $resueltos = (clone $base)->whereBetween('resuelto_en', [$desde, $hasta])
            ->get(['id', 'created_at', 'resuelto_en', 'fecha_vencimiento_sla', 'minutos_pausados']);

        $conPlazo = $resueltos->filter(fn (Ticket $t) => $t->fecha_vencimiento_sla !== null);
        $enPlazo = $conPlazo->filter(fn (Ticket $t) => $t->resuelto_en->lte($t->fecha_vencimiento_sla))->count();
        $horas = $resueltos->map(fn (Ticket $t) => max(0, $this->calendario->horasEntre($t->created_at, $t->resuelto_en) - $t->minutos_pausados / 60));

        $encuestas = EncuestaSatisfaccion::whereIn('ticket_id', (clone $base)->select('tickets.id'))
            ->whereBetween('created_at', [$desde, $hasta]);
        $promedio = (clone $encuestas)->avg('calificacion');

        return [
            'abiertos'         => (clone $base)->whereBetween('created_at', [$desde, $hasta])->count(),
            'resueltos'        => $resueltos->count(),
            'cumplimiento_sla' => $conPlazo->isEmpty() ? null : (int) round(100 * $enPlazo / $conPlazo->count()),
            'horas_resolucion' => $horas->isEmpty() ? null : round($horas->avg(), 1),
            'satisfaccion'     => $promedio === null ? null : round((float) $promedio, 1),
            'encuestas'        => $encuestas->count(),
        ];
    }
}
