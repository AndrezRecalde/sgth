<?php

namespace App\Services\InventarioTi\Prestamos;

use App\Enums\EstadoBien;
use App\Enums\EstadoPrestamo;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\HistorialEstadoPrestamo;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\PrestamoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Lo que comparten la solicitud, el préstamo directo y el trámite: qué equipos
 * se pueden prestar, si están libres en un horario, si están en TIC, ocupar y
 * liberar su horario, y anotar cada cambio de estado.
 */
final class EquiposDelPrestamo
{
    /** Los estados en que un equipo prestable no sale de TIC. */
    private const NO_PRESTABLES = [
        EstadoBien::DADO_DE_BAJA, EstadoBien::EXTRAVIADO, EstadoBien::EN_MANTENIMIENTO, EstadoBien::EN_GARANTIA,
    ];

    /**
     * Los equipos pedidos, bloqueados para el resto de la transacción, si
     * todos se pueden prestar.
     *
     * @param  list<int>  $ids
     * @return Collection<int, BienInformatico>
     */
    public function prestables(array $ids): Collection
    {
        $bienes = BienInformatico::with('tipo:id,nombre')->whereKey($ids)->orderBy('codigo_institucional')->lockForUpdate()->get();

        $problemas = $bienes->map(fn (BienInformatico $b) => match (true) {
            ! $b->prestable => "{$b->codigo_institucional} no está en el grupo de préstamo",
            in_array($b->estado_operativo, self::NO_PRESTABLES, true) => "{$b->codigo_institucional} está " . mb_strtolower($b->estado_operativo->etiqueta()),
            default => null,
        })->filter();

        if ($bienes->count() !== count(array_unique($ids))) {
            $problemas->push('uno de los equipos ya no está en el inventario');
        }

        if ($problemas->isNotEmpty()) {
            throw ValidationException::withMessages(['bienes' => 'No se puede prestar: ' . $problemas->implode('; ') . '.']);
        }

        return $bienes;
    }

    /**
     * Que ningún préstamo reservado o en curso ocupe esos equipos en ese
     * horario. La restricción de exclusión lo garantiza al final; esto da el
     * mensaje con el detalle.
     *
     * @param  list<int>  $ids
     */
    public function exigirLibres(array $ids, Carbon $inicio, Carbon $fin, ?int $salvoPrestamo = null): void
    {
        $choques = PrestamoItem::with('bien:id,codigo_institucional')
            ->whereIn('bien_informatico_id', $ids)
            ->where('bloquea', true)
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->when($salvoPrestamo, fn (Builder $q, $id) => $q->where('prestamo_equipo_id', '!=', $id))
            ->orderBy('inicio')
            ->get();

        if ($choques->isNotEmpty()) {
            throw ValidationException::withMessages(['bienes' => 'Ese horario ya está ocupado: ' . $choques
                ->map(fn (PrestamoItem $i) => "{$i->bien->codigo_institucional} de {$this->rango($i->inicio, $i->fin)}")
                ->implode('; ') . '.']);
        }
    }

    /**
     * Que los equipos estén en TIC: un préstamo vencido no ocupa su horario
     * previsto, pero el equipo sigue fuera hasta que vuelve.
     *
     * @param  list<int>  $ids
     */
    public function exigirEnTic(array $ids, ?int $salvoPrestamo = null): void
    {
        $fuera = PrestamoItem::with('bien:id,codigo_institucional', 'prestamo:id,folio')
            ->whereIn('bien_informatico_id', $ids)
            ->whereHas('prestamo', fn (Builder $q) => $q->where('estado', EstadoPrestamo::EN_CURSO))
            ->when($salvoPrestamo, fn (Builder $q, $id) => $q->where('prestamo_equipo_id', '!=', $id))
            ->get();

        if ($fuera->isNotEmpty()) {
            throw new ReglaNegocioException('Todavía no vuelve: ' . $fuera
                ->map(fn (PrestamoItem $i) => "{$i->bien->codigo_institucional} (préstamo {$i->prestamo->folio})")
                ->implode(', ') . '. Registre esa devolución primero.');
        }
    }

    /** Ocupa o libera el horario de los equipos del préstamo, según su estado. */
    public function sincronizarBloqueo(PrestamoEquipo $prestamo): void
    {
        try {
            PrestamoItem::where('prestamo_equipo_id', $prestamo->id)->update(['bloquea' => $prestamo->estado->bloquea()]);
        } catch (QueryException $e) {
            // 23P01: la restricción de exclusión. Otro préstamo ganó el horario
            // entre la comprobación y la escritura.
            if ($e->getCode() === '23P01') {
                throw new ReglaNegocioException('Otro préstamo acaba de reservar alguno de esos equipos en ese horario. Revise la disponibilidad.');
            }
            throw $e;
        }
    }

    public function anotar(PrestamoEquipo $prestamo, ?EstadoPrestamo $anterior, ?string $detalle = null): void
    {
        HistorialEstadoPrestamo::create([
            'prestamo_equipo_id' => $prestamo->id,
            'estado_anterior'    => $anterior,
            'estado_nuevo'       => $prestamo->estado,
            'user_id'            => auth()->id(),
            'detalle'            => $detalle,
        ]);
    }

    public function rango(Carbon $inicio, Carbon $fin): string
    {
        return $inicio->isSameDay($fin)
            ? $inicio->format('d/m H:i') . ' a ' . $fin->format('H:i')
            : $inicio->format('d/m H:i') . ' al ' . $fin->format('d/m H:i');
    }
}
