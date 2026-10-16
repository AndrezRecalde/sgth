<?php

namespace App\Services\Tic\Reportes;

use App\Enums\TipoMovimientoConsumible;
use App\Models\InventarioTi\MovimientoConsumible;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * El consumo de consumibles en un período (fase 5.4d): las entregas vivas,
 * agrupadas por impresora, por área o por consumible. El área es la que la
 * entrega guardó en su momento, así que el consumo no se mueve si el servidor
 * cambia de unidad. Cuenta lo que guarda TIC y lo que despacha Bodega.
 */
final class ReporteConsumosService
{
    public const AGRUPACIONES = ['impresora', 'area', 'consumible'];

    /**
     * @param  array{agrupar?: string|null, tipo?: string|null}  $filtros
     * @return array<string, mixed>
     */
    public function generar(CarbonInterface $desde, CarbonInterface $hasta, array $filtros = []): array
    {
        $agrupar = in_array($filtros['agrupar'] ?? null, self::AGRUPACIONES, true) ? $filtros['agrupar'] : 'consumible';

        $filas = MovimientoConsumible::with([
            'consumible:id,descripcion,tipo,color,codigo_bodega,guarda_tic',
            'bien:id,codigo_institucional,marca_id,modelo', 'bien.marca:id,nombre',
            'unidad:id,nombre', 'retira:id,nombre,segundo_nombre,apellido,segundo_apellido', 'ticket:id,codigo_ticket',
        ])
            ->where('tipo', TipoMovimientoConsumible::ENTREGA)
            ->vivos()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($filtros['tipo'] ?? null, fn ($q, $t) => $q->whereHas('consumible', fn ($c) => $c->where('tipo', $t)))
            ->orderBy('fecha')->orderBy('id')
            ->get()
            ->map(fn (MovimientoConsumible $m) => $this->fila($m));

        $clave = match ($agrupar) {
            'impresora' => fn (array $f) => $f['equipo'] ? "{$f['equipo']} {$f['equipo_modelo']}" : 'Sin equipo',
            'area'      => fn (array $f) => $f['unidad'] ?? 'Sin unidad',
            default     => fn (array $f) => $f['consumible'],
        };
        // Dentro de cada grupo, el desglose por lo otro que más se pregunta.
        $desglose = $agrupar === 'consumible'
            ? fn (array $f) => $f['unidad'] ?? 'Sin unidad'
            : fn (array $f) => $f['consumible'];

        $grupos = $filas->groupBy($clave)
            ->map(fn (Collection $g, int|string $nombre) => [
                'nombre'   => (string) $nombre,
                'cantidad' => $g->sum('cantidad'),
                'entregas' => $g->count(),
                'desglose' => $g->groupBy($desglose)
                    ->map(fn (Collection $d, int|string $n) => ['nombre' => (string) $n, 'cantidad' => $d->sum('cantidad')])
                    ->sortByDesc('cantidad')->values()->all(),
            ])
            ->sortByDesc('cantidad')
            ->values();

        return [
            'desde'        => $desde->toDateString(),
            'hasta'        => $hasta->toDateString(),
            'agrupado_por' => $agrupar,
            'cantidad'     => $filas->sum('cantidad'),
            'entregas'     => $filas->count(),
            'grupos'       => $grupos->all(),
            'filas'        => $filas->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function fila(MovimientoConsumible $m): array
    {
        return [
            'id'            => $m->id,
            'fecha'         => $m->fecha->toDateString(),
            'consumible_id' => $m->consumible_id,
            'consumible'    => $m->consumible?->descripcion,
            'codigo_bodega' => $m->consumible?->codigo_bodega,
            'tipo'          => $m->consumible?->tipo->etiqueta(),
            'guarda_tic'    => (bool) $m->consumible?->guarda_tic,
            'cantidad'      => -$m->cantidad,
            'equipo'        => $m->bien?->codigo_institucional,
            'equipo_modelo' => trim(($m->bien?->marca?->nombre ?? '') . ' ' . ($m->bien?->modelo ?? '')),
            'unidad'        => $m->unidad?->nombre,
            'retira'        => $m->retira?->nombre_completo,
            'ticket'        => $m->ticket?->codigo_ticket,
        ];
    }
}
