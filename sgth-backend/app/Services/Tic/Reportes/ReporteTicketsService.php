<?php

namespace App\Services\Tic\Reportes;

use App\Enums\EstadoTicket;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use App\Services\Helpdesk\Tickets\MetricasTickets;
use Carbon\CarbonInterface;

/**
 * La mesa de ayuda en un rango, por técnico y por categoría (fase 4.2):
 * cuántos tickets recibió cada uno, cuántos resolvió, cuántos dentro del
 * plazo, cuánto tardó y cómo lo calificaron. Las cifras salen de
 * `MetricasTickets`, las mismas del tablero.
 */
final class ReporteTicketsService
{
    public function __construct(private readonly MetricasTickets $metricas) {}

    /** @return array{desde: string, hasta: string, totales: array<string, mixed>, por_tecnico: list<array<string, mixed>>, por_categoria: list<array<string, mixed>>} */
    public function generar(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $enRango = fn () => Ticket::where(fn ($q) => $q->whereBetween('created_at', [$desde, $hasta])->orWhereBetween('resuelto_en', [$desde, $hasta]));

        // Los técnicos con trabajo en el rango y los registrados, aunque no
        // hayan tenido tickets: que alguien tenga cero también se ve.
        $tecnicos = User::with('servidor:id,nombre,apellido')
            ->whereIn('id', $enRango()->whereNotNull('tecnico_id')->select('tecnico_id'))
            ->orWhereIn('id', TecnicoDtic::where('estado', true)->select('user_id'))
            ->get()
            ->sortBy(fn (User $u) => $u->nombre_completo)
            ->map(fn (User $u) => [
                'tecnico'  => $u->nombre_completo,
                ...$this->cifras(Ticket::where('tecnico_id', $u->id), $desde, $hasta),
                'en_curso' => Ticket::where('tecnico_id', $u->id)->whereIn('estado', EstadoTicket::enCurso())->count(),
            ])
            ->values();

        $categorias = CategoriaTicket::whereIn('id', $enRango()->select('categoria_id'))->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (CategoriaTicket $c) => ['categoria' => $c->nombre, ...$this->cifras(Ticket::where('categoria_id', $c->id), $desde, $hasta)])
            ->values();

        return [
            'desde'         => $desde->toDateString(),
            'hasta'         => $hasta->toDateString(),
            'totales'       => $this->cifras(Ticket::query(), $desde, $hasta),
            'por_tecnico'   => $tecnicos->all(),
            'por_categoria' => $categorias->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function cifras($base, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return [
            ...$this->metricas->delPeriodo($base, $desde, $hasta),
            // Volvieron a abrirse después de darse por resueltos.
            'reabiertos' => (clone $base)->whereBetween('resuelto_en', [$desde, $hasta])->where('reabierto_veces', '>', 0)->count(),
        ];
    }
}
