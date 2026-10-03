<?php

namespace App\Services\Dispensario\Reportes;

use App\Services\Dispensario\InventarioMedicinasService;
use Illuminate\Support\Facades\DB;

/**
 * Lo que hay hoy en Farmacia, lote por lote, con su caducidad y su valor.
 *
 * Es una foto de hoy, no un período. Va por lote porque lo que caduca es el
 * lote. El valor sale del precio de la adquisición que lo trajo; el stock
 * inicial y los ajustes al alza no tienen precio y lo dicen. El aviso de
 * «por caducar» usa los mismos días que las alertas de Farmacia, y el día de
 * la caducidad el lote todavía se despacha.
 */
final class ExistenciasReporte extends ReporteBase
{
    public function clave(): string { return 'farmacia_existencias'; }

    public function titulo(): string { return 'Existencias y caducidades'; }

    public function descripcion(): string
    {
        return 'Stock de hoy por lote, con su caducidad y su valor.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD];
    }

    public function filtros(): array
    {
        return [];
    }

    public function usaPeriodo(): bool
    {
        return false;
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([
            ['codigo', 'Código'], ['medicamento', 'Medicamento'], ['presentacion', 'Presentación'],
            ['lote', 'Lote'], ['caducidad', 'Caduca el'], ['estado', 'Estado'],
            ['stock', 'Stock'], ['precio_unitario', 'Precio unitario'], ['valor', 'Valor'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $hoy = today();
        $aviso = $hoy->copy()->addDays(InventarioMedicinasService::DIAS_AVISO_CADUCIDAD);

        return DB::table('lotes_medicina as l')
            ->join('inventario_medicinas as i', 'i.id', '=', 'l.inventario_medicina_id')
            ->leftJoin('items_adquisicion as ia', 'ia.id', '=', 'l.item_adquisicion_id')
            ->whereNull('i.deleted_at')
            ->where('l.stock_actual', '>', 0)
            ->orderByRaw('l.fecha_caducidad NULLS LAST')->orderBy('i.nombre')->orderBy('l.id')
            ->get([
                'i.codigo', 'i.nombre', 'i.concentracion', 'i.presentacion',
                'l.codigo_lote', 'l.fecha_caducidad', 'l.stock_actual', 'ia.precio_unitario',
            ])
            ->map(function ($l) use ($hoy, $aviso) {
                $caduca = $l->fecha_caducidad ? \Carbon\Carbon::parse($l->fecha_caducidad) : null;
                $precio = $l->precio_unitario !== null ? (float) $l->precio_unitario : null;

                return [
                    'codigo'          => $l->codigo,
                    'medicamento'     => trim($l->nombre . ' ' . ($l->concentracion ?? '')),
                    'presentacion'    => $l->presentacion,
                    'lote'            => $l->codigo_lote ?? 'Sin identificar',
                    'caducidad'       => $caduca?->toDateString(),
                    'estado'          => match (true) {
                        $caduca === null       => 'Sin fecha',
                        $caduca->lt($hoy)      => 'Caducado',
                        $caduca->lte($aviso)   => 'Por caducar',
                        default                => 'Vigente',
                    },
                    'stock'           => (int) $l->stock_actual,
                    'precio_unitario' => $precio,
                    'valor'           => $precio !== null ? round($precio * $l->stock_actual, 2) : null,
                ];
            })
            ->all();
    }
}
