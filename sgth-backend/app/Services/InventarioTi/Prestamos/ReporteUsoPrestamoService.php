<?php

namespace App\Services\InventarioTi\Prestamos;

use App\Enums\EstadoBien;
use App\Enums\EstadoPrestamo;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\PrestamoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El uso de cada equipo de préstamo en un rango (fase 2.4): cuántas veces
 * salió, cuántas horas estuvo fuera, cuántas volvió tarde y cuántas reservas
 * no se retiraron. Sirve para decidir si hacen falta más proyectores o si uno
 * casi no se pide.
 *
 * Cuenta los préstamos entregados en el rango; las horas de uno que sigue
 * fuera llegan hasta ahora.
 */
final class ReporteUsoPrestamoService
{
    /** @return array{desde: string, hasta: string, equipos: list<array<string, mixed>>, totales: array<string, float|int>} */
    public function generar(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();
        if ($fin->lt($inicio) || $inicio->diffInDays($fin) > 366) {
            throw ValidationException::withMessages(['hasta' => 'El reporte cubre hasta un año.']);
        }

        $items = PrestamoItem::with('prestamo:id,estado,inicio_previsto,fin_previsto,entregado_en,devuelto_en')
            ->whereHas('prestamo', fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->whereBetween('entregado_en', [$inicio, $fin])
                    ->orWhere(fn (Builder $n) => $n->where('estado', EstadoPrestamo::NO_RETIRADO)->whereBetween('inicio_previsto', [$inicio, $fin]))))
            ->get()
            ->groupBy('bien_informatico_id');

        // Los prestables de hoy, y los que se prestaron en el rango aunque ya no lo sean.
        $bienes = BienInformatico::withTrashed()->with('tipo:id,nombre', 'marca:id,nombre')
            ->where(fn (Builder $q) => $q->where(fn (Builder $p) => $p->where('prestable', true)->where('estado_operativo', '!=', EstadoBien::DADO_DE_BAJA))
                ->orWhereIn('id', $items->keys()))
            ->orderBy('codigo_institucional')
            ->get();

        $equipos = $bienes->map(fn (BienInformatico $b) => $this->fila($b, $items->get($b->id, collect())))->values();

        return [
            'desde'   => $inicio->toDateString(),
            'hasta'   => $fin->toDateString(),
            'equipos' => $equipos->all(),
            'totales' => [
                'prestamos'    => $equipos->sum('prestamos'),
                'horas'        => round($equipos->sum('horas'), 1),
                'devoluciones_tarde' => $equipos->sum('devoluciones_tarde'),
                'no_retirados' => $equipos->sum('no_retirados'),
            ],
        ];
    }

    /** @param  Collection<int, PrestamoItem>  $items */
    private function fila(BienInformatico $bien, Collection $items): array
    {
        $entregados = $items->filter(fn (PrestamoItem $i) => $i->prestamo->entregado_en !== null);
        $ahora = now();

        return [
            'id'                   => $bien->id,
            'codigo_institucional' => $bien->codigo_institucional,
            'tipo'                 => $bien->tipo?->nombre,
            'marca'                => $bien->marca?->nombre,
            'modelo'               => $bien->modelo,
            'prestable'            => (bool) $bien->prestable,
            'prestamos'            => $entregados->count(),
            'horas'                => round($entregados->sum(fn (PrestamoItem $i) =>
                $i->prestamo->entregado_en->diffInMinutes($i->prestamo->devuelto_en ?? $ahora)) / 60, 1),
            // Volvió después de la hora prevista, o sigue fuera con la hora pasada.
            'devoluciones_tarde'   => $entregados->filter(fn (PrestamoItem $i) =>
                ($i->prestamo->devuelto_en ?? $ahora)->gt($i->prestamo->fin_previsto))->count(),
            'no_retirados'         => $items->filter(fn (PrestamoItem $i) => $i->prestamo->estado === EstadoPrestamo::NO_RETIRADO)->count(),
            'ultimo_prestamo'      => $entregados->max(fn (PrestamoItem $i) => $i->prestamo->entregado_en)?->toIso8601String(),
        ];
    }
}
