<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Lo que entró y salió de cada medicamento en el período, del kardex.
 *
 * Despachado es lo entregado en recetas (`egreso`); dado de baja, lo retirado
 * por caducidad o daño (`baja`); ajustes, la diferencia de los conteos
 * físicos; anulado, lo que se devolvió al anular una adquisición o una
 * receta. Las salidas se muestran en positivo: «se despacharon 30».
 */
final class MovimientoMedicamentosReporte extends ReporteBase
{
    public function clave(): string { return 'farmacia_movimiento'; }

    public function titulo(): string { return 'Movimiento de medicamentos'; }

    public function descripcion(): string
    {
        return 'Ingresos, despachos, bajas y ajustes de cada medicamento en el período.';
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

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([
            ['codigo', 'Código'], ['medicamento', 'Medicamento'], ['presentacion', 'Presentación'],
            ['ingresos', 'Ingresos'], ['despachado', 'Despachado'], ['recetas', 'Recetas atendidas'],
            ['bajas', 'Dado de baja'], ['ajustes', 'Ajustes (neto)'], ['anulaciones', 'Anulaciones (neto)'],
            ['stock_actual', 'Stock actual'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        return DB::table('movimientos_inventario_med as m')
            ->join('inventario_medicinas as i', 'i.id', '=', 'm.inventario_medicina_id')
            ->whereBetween('m.created_at', [$filtros->desde, $filtros->hasta])
            ->groupBy('i.id', 'i.codigo', 'i.nombre', 'i.presentacion', 'i.concentracion', 'i.stock_actual')
            ->selectRaw("
                i.codigo, i.nombre, i.presentacion, i.concentracion, i.stock_actual,
                COALESCE(SUM(m.cantidad) FILTER (WHERE m.tipo_movimiento = 'ingreso'), 0) as ingresos,
                COALESCE(-SUM(m.cantidad) FILTER (WHERE m.tipo_movimiento = 'egreso'), 0) as despachado,
                COUNT(DISTINCT m.referencia_receta_id) FILTER (WHERE m.tipo_movimiento = 'egreso') as recetas,
                COALESCE(-SUM(m.cantidad) FILTER (WHERE m.tipo_movimiento = 'baja'), 0) as bajas,
                COALESCE(SUM(m.cantidad) FILTER (WHERE m.tipo_movimiento = 'ajuste'), 0) as ajustes,
                COALESCE(SUM(m.cantidad) FILTER (WHERE m.tipo_movimiento = 'anulacion'), 0) as anulaciones
            ")
            ->orderByDesc('despachado')->orderBy('i.nombre')->orderBy('i.id')
            ->get()
            ->map(fn ($f) => [
                'codigo'       => $f->codigo,
                'medicamento'  => trim($f->nombre . ' ' . ($f->concentracion ?? '')),
                'presentacion' => $f->presentacion,
                'ingresos'     => (int) $f->ingresos,
                'despachado'   => (int) $f->despachado,
                'recetas'      => (int) $f->recetas,
                'bajas'        => (int) $f->bajas,
                'ajustes'      => (int) $f->ajustes,
                'anulaciones'  => (int) $f->anulaciones,
                'stock_actual' => (int) $f->stock_actual,
            ])
            ->all();
    }
}
