<?php

namespace App\Services\Tic\Reportes;

use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\MovimientoConsumible;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El saldo de lo que guarda TIC al cierre de una fecha (fase 5.4d), para
 * cuadrar con Bodega en el cierre del año. Cuenta los movimientos vivos hasta
 * ese día; lo anulado después tampoco cuenta, porque se registró por error.
 */
final class ReporteSaldoConsumiblesService
{
    /** @return array<string, mixed> */
    public function generar(CarbonInterface $fecha): array
    {
        $saldos = MovimientoConsumible::query()
            ->where('afecta_saldo', true)->whereNull('anulado_en')->where('fecha', '<=', $fecha->toDateString())
            ->selectRaw('consumible_id, SUM(cantidad) AS saldo, MAX(fecha) AS ultimo')
            ->groupBy('consumible_id')
            ->get()->keyBy('consumible_id');

        // Los que guarda TIC; de los inactivos, solo los que todavía tenían algo ese día.
        $filas = Consumible::where('guarda_tic', true)
            ->orderBy('descripcion')->orderBy('id')
            ->get()
            ->map(function (Consumible $c) use ($saldos) {
                $saldo = (int) ($saldos[$c->id]->saldo ?? 0);

                return [
                    'id'            => $c->id,
                    'codigo_bodega' => $c->codigo_bodega,
                    'descripcion'   => $c->descripcion,
                    'tipo'          => $c->tipo->etiqueta(),
                    'presentacion'  => $c->presentacion->etiqueta(),
                    'activo'        => $c->activo,
                    'saldo'         => $saldo,
                    'stock_minimo'  => $c->stock_minimo,
                    'bajo_minimo'   => $c->bajoMinimo($saldo),
                    'ultimo_movimiento' => isset($saldos[$c->id]) ? Carbon::parse($saldos[$c->id]->ultimo)->toDateString() : null,
                ];
            })
            ->filter(fn (array $f) => $f['activo'] || $f['saldo'] !== 0)
            ->values();

        return [
            'fecha'       => $fecha->toDateString(),
            'consumibles' => $filas->count(),
            'unidades'    => $filas->sum('saldo'),
            'bajo_minimo' => $filas->where('bajo_minimo', true)->count(),
            'filas'       => $filas->all(),
        ];
    }
}
