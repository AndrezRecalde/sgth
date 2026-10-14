<?php

namespace App\Services\Tic\Reportes;

use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que dijeron los usuarios al confirmar sus tickets (fase 4.2): el
 * promedio, cuántas encuestas hubo de cada nota, por técnico, por categoría
 * y los comentarios. Reemplaza los resultados simulados de
 * `encuestas-satisfaccion/resultados`, que devolvían 4.5 para todo.
 */
final class ReporteSatisfaccionService
{
    /**
     * @return array{desde: string, hasta: string, encuestas: int, promedio: ?float, distribucion: array<int, int>, por_tecnico: list<array<string, mixed>>, por_categoria: list<array<string, mixed>>, comentarios: list<array<string, mixed>>}
     */
    public function generar(CarbonInterface $desde, CarbonInterface $hasta, ?int $tecnicoId = null): array
    {
        $encuestas = EncuestaSatisfaccion::with([
            'ticket:id,codigo_ticket,tecnico_id,categoria_id',
            'ticket.tecnico:id,usuario_ti,email,servidor_id', 'ticket.tecnico.servidor:id,nombre,apellido',
            'ticket.categoria:id,nombre',
        ])
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($tecnicoId, fn (Builder $q, int $id) => $q->whereHas('ticket', fn (Builder $t) => $t->where('tecnico_id', $id)))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $promedio = fn (Collection $grupo) => $grupo->isEmpty() ? null : round((float) $grupo->avg('calificacion'), 1);
        $agrupar = fn (callable $clave, string $campo) => $encuestas->groupBy($clave)
            ->map(fn (Collection $grupo, string $nombre) => [$campo => $nombre, 'encuestas' => $grupo->count(), 'promedio' => $promedio($grupo)])
            ->sortBy($campo)->values()->all();

        return [
            'desde'         => $desde->toDateString(),
            'hasta'         => $hasta->toDateString(),
            'encuestas'     => $encuestas->count(),
            'promedio'      => $promedio($encuestas),
            'distribucion'  => collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $n) => [$n => $encuestas->where('calificacion', $n)->count()])->all(),
            'por_tecnico'   => $agrupar(fn (EncuestaSatisfaccion $e) => $this->nombre($e->ticket?->tecnico) ?? 'Sin técnico', 'tecnico'),
            'por_categoria' => $agrupar(fn (EncuestaSatisfaccion $e) => $e->ticket?->categoria?->nombre ?? 'Sin categoría', 'categoria'),
            'comentarios'   => $encuestas->filter(fn (EncuestaSatisfaccion $e) => filled($e->comentarios))
                ->map(fn (EncuestaSatisfaccion $e) => [
                    'id'           => $e->id,
                    'ticket_id'    => $e->ticket_id,
                    'folio'        => $e->ticket?->codigo_ticket,
                    'fecha'        => $e->created_at->toIso8601String(),
                    'calificacion' => $e->calificacion,
                    'tecnico'      => $this->nombre($e->ticket?->tecnico),
                    'categoria'    => $e->ticket?->categoria?->nombre,
                    'comentario'   => $e->comentarios,
                ])->values()->all(),
        ];
    }

    private function nombre(?User $user): ?string
    {
        return $user?->nombre_completo;
    }
}
