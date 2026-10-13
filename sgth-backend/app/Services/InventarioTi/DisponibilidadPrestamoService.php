<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\DisponibilidadPrestamoServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\EstadoPrestamo;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\PrestamoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * La disponibilidad de los equipos prestables (docs/diseno-subsistema-tic.md,
 * 4.6): cómo está cada uno ahora y qué bloques tiene ocupados en el rango.
 *
 * Ahora:
 * - `no_disponible`: en mantenimiento, en garantía o extraviado;
 * - `prestado`: en un préstamo en curso, hasta su hora prevista (o vencido);
 * - `reservado`: reservado para un horario que ya empezó y aún no se retira;
 * - `disponible`: con la próxima reserva, si la hay.
 *
 * El Portal ve que está ocupado y hasta cuándo; quién lo tiene, solo TIC (D15).
 */
final class DisponibilidadPrestamoService implements DisponibilidadPrestamoServiceInterface
{
    /** El rango más largo que se consulta de una vez: una agenda mensual. */
    private const MAXIMO_DIAS = 31;

    public function equipos(string $desde, string $hasta, bool $paraTic, ?int $tipoBienId = null): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();
        if ($fin->lt($inicio) || $inicio->diffInDays($fin) > self::MAXIMO_DIAS) {
            throw ValidationException::withMessages(['hasta' => 'Consulte hasta ' . self::MAXIMO_DIAS . ' días de una vez.']);
        }

        $bienes = BienInformatico::with('tipo:id,nombre', 'marca:id,nombre')
            ->where('prestable', true)
            ->where('estado_operativo', '!=', EstadoBien::DADO_DE_BAJA)
            ->when($tipoBienId, fn (Builder $q, $t) => $q->where('tipo_bien_id', $t))
            ->orderBy('tipo_bien_id')
            ->orderBy('codigo_institucional')
            ->get();

        $ids = $bienes->pluck('id');
        $enCurso = $this->items($ids)->whereHas('prestamo', fn (Builder $q) => $q->where('estado', EstadoPrestamo::EN_CURSO))
            ->get()->keyBy('bien_informatico_id');
        $bloques = $this->items($ids)->where('bloquea', true)
            ->where('inicio', '<', $fin)->where('fin', '>', $inicio)
            ->orderBy('inicio')->get()->groupBy('bien_informatico_id');
        $proximas = $this->items($ids)->where('bloquea', true)->where('inicio', '>', now())
            ->orderBy('inicio')->get()->unique('bien_informatico_id')->keyBy('bien_informatico_id');

        return $bienes->map(fn (BienInformatico $b) => [
            'id'                   => $b->id,
            'codigo_institucional' => $b->codigo_institucional,
            'tipo'                 => $b->tipo?->nombre,
            'tipo_bien_id'         => $b->tipo_bien_id,
            'marca'                => $b->marca?->nombre,
            'modelo'               => $b->modelo,
            'ahora'                => $this->ahora($b, $enCurso->get($b->id), $bloques->get($b->id, collect()), $proximas->get($b->id), $paraTic),
            'bloques'              => $bloques->get($b->id, collect())->map(fn (PrestamoItem $i) => $this->bloque($i, $paraTic))->values()->all(),
        ])->values()->all();
    }

    private function items(Collection $ids): Builder
    {
        return PrestamoItem::with('prestamo:id,folio,estado,servidor_id,fin_previsto', 'prestamo.servidor:id,nombre,apellido')
            ->whereIn('bien_informatico_id', $ids);
    }

    /** @param  Collection<int, PrestamoItem>  $bloques */
    private function ahora(BienInformatico $bien, ?PrestamoItem $enCurso, Collection $bloques, ?PrestamoItem $proxima, bool $paraTic): array
    {
        if (in_array($bien->estado_operativo, [EstadoBien::EN_MANTENIMIENTO, EstadoBien::EN_GARANTIA, EstadoBien::EXTRAVIADO], true)) {
            return ['estado' => 'no_disponible', 'detalle' => $bien->estado_operativo->etiqueta()];
        }

        if ($enCurso) {
            return [
                'estado'  => 'prestado',
                'hasta'   => $enCurso->fin,
                'vencido' => $enCurso->fin->isPast(),
                ...$this->quien($enCurso, $paraTic),
            ];
        }

        $reservaAhora = $bloques->first(fn (PrestamoItem $i) => $i->inicio->lte(now()) && $i->fin->gt(now()));
        if ($reservaAhora) {
            return ['estado' => 'reservado', 'desde' => $reservaAhora->inicio, 'hasta' => $reservaAhora->fin, ...$this->quien($reservaAhora, $paraTic)];
        }

        return ['estado' => 'disponible', 'proxima_reserva' => $proxima?->inicio];
    }

    private function bloque(PrestamoItem $item, bool $paraTic): array
    {
        return [
            'inicio' => $item->inicio,
            'fin'    => $item->fin,
            'estado' => $item->prestamo->estado->value,
            ...$this->quien($item, $paraTic),
        ];
    }

    /** El folio y quién lo tiene, solo para TIC. */
    private function quien(PrestamoItem $item, bool $paraTic): array
    {
        if (! $paraTic) {
            return [];
        }

        $s = $item->prestamo->servidor;

        return [
            'prestamo_id' => $item->prestamo_equipo_id,
            'folio'       => $item->prestamo->folio,
            'servidor'    => $s ? trim("{$s->apellido} {$s->nombre}") : null,
        ];
    }
}
