<?php

namespace App\Exports\Tic;

use App\Enums\DocumentoIngresoConsumible;
use App\Enums\MotivoAjusteConsumible;

/**
 * Las hojas de cada reporte de TIC (fase 4.2), a partir de lo que devuelve su
 * servicio: lo que se ve en pantalla es lo que se descarga.
 */
final class ReportesTicExcel
{
    /** @param  array<string, mixed>  $r  `ReporteInventarioService::generar` */
    public static function inventario(array $r): LibroReporteTic
    {
        $grupo = $r['agrupado_por'] === 'ubicacion' ? 'Ubicación' : 'Unidad';
        $filas = [];
        foreach ($r['grupos'] as $g) {
            foreach ($g['equipos'] as $e) {
                $filas[] = [
                    $g['nombre'], $e['codigo_institucional'], $e['tipo'], $e['marca'], $e['modelo'], $e['numero_serie'],
                    $e['estado'], $e['condicion'], $e['custodio'], $e['usuario'],
                    $r['agrupado_por'] === 'ubicacion' ? $e['unidad'] : $e['ubicacion'],
                    LibroReporteTic::fecha($e['fecha_compra']),
                ];
            }
        }
        $resumen = array_map(fn (array $g) => [$g['nombre'], $g['total']], $r['grupos']);
        $resumen[] = ['Total', $r['total']];

        return new LibroReporteTic([
            new HojaReporteTic("Por {$grupo}", [$grupo, 'Equipos'], $resumen),
            new HojaReporteTic('Equipos', [
                $grupo, 'Código', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Estado', 'Condición', 'Custodio', 'Usuario',
                $r['agrupado_por'] === 'ubicacion' ? 'Unidad' : 'Ubicación', 'Fecha de compra',
            ], $filas),
        ]);
    }

    /** @param  array<string, mixed>  $r  `ReporteObsolescenciaService::generar` */
    public static function obsolescencia(array $r): LibroReporteTic
    {
        $resumen = array_map(fn (array $t) => [
            $t['tipo'], $t['anios_vida_util'] ?? 'Sin definir', $t['en_servicio'], $t['cumplida'], $t['proxima'], $t['sin_fecha'],
        ], $r['resumen']);
        $tot = $r['totales'];
        $resumen[] = ['Total', '', $tot['en_servicio'], $tot['cumplida'], $tot['proxima'], $tot['sin_fecha']];

        return new LibroReporteTic([
            new HojaReporteTic('Resumen por tipo', [
                'Tipo', 'Vida útil (años)', 'En servicio', 'Vida útil cumplida', 'Se cumple en 12 meses', 'Sin fin de vida útil',
            ], $resumen),
            new HojaReporteTic('Equipos', [
                'Código', 'Tipo', 'Marca', 'Modelo', 'Fecha de compra', 'Años', 'Fin de vida útil', 'Situación',
                'Estado', 'Condición', 'Correctivos (12 meses)', 'Custodio', 'Unidad',
            ], array_map(fn (array $e) => [
                $e['codigo_institucional'], $e['tipo'], $e['marca'], $e['modelo'], LibroReporteTic::fecha($e['fecha_compra']), $e['anios'],
                LibroReporteTic::fecha($e['fin_vida_util']), $e['situacion'] === 'cumplida' ? 'Cumplida' : 'Próxima',
                $e['estado'], $e['condicion'], $e['correctivos'], $e['custodio'], $e['unidad'],
            ], $r['equipos'])),
        ]);
    }

    /** @param  array<string, mixed>  $r  `ReporteTicketsService::generar` */
    public static function tickets(array $r): LibroReporteTic
    {
        $encabezados = fn (string $primero) => [
            $primero, 'Recibidos', 'Resueltos', 'Dentro del plazo (%)', 'Tiempo de resolución (h hábiles)', 'Reabiertos', 'Satisfacción (1-5)', 'Encuestas',
        ];
        $fila = fn (string $nombre, array $c) => [
            $nombre, $c['abiertos'], $c['resueltos'], $c['cumplimiento_sla'], $c['horas_resolucion'], $c['reabiertos'], $c['satisfaccion'], $c['encuestas'],
        ];
        $tecnicos = array_map(fn (array $t) => [...$fila($t['tecnico'], $t), $t['en_curso']], $r['por_tecnico']);
        $categorias = array_map(fn (array $c) => $fila($c['categoria'], $c), $r['por_categoria']);
        $categorias[] = $fila('Total', $r['totales']);

        return new LibroReporteTic([
            new HojaReporteTic('Por técnico', [...$encabezados('Técnico'), 'En curso hoy'], $tecnicos),
            new HojaReporteTic('Por categoría', $encabezados('Categoría'), $categorias),
        ]);
    }

    /** @param  array<string, mixed>  $r  `ReporteSatisfaccionService::generar` */
    public static function satisfaccion(array $r): LibroReporteTic
    {
        $resumen = [['Encuestas', $r['encuestas']], ['Promedio (1-5)', $r['promedio']]];
        foreach ($r['distribucion'] as $nota => $total) {
            $resumen[] = ["{$nota} " . ($nota === 1 ? 'estrella' : 'estrellas'), $total];
        }

        return new LibroReporteTic([
            new HojaReporteTic('Resumen', ['Indicador', 'Valor'], $resumen),
            new HojaReporteTic('Por técnico', ['Técnico', 'Encuestas', 'Promedio'],
                array_map(fn (array $t) => [$t['tecnico'], $t['encuestas'], $t['promedio']], $r['por_tecnico'])),
            new HojaReporteTic('Por categoría', ['Categoría', 'Encuestas', 'Promedio'],
                array_map(fn (array $c) => [$c['categoria'], $c['encuestas'], $c['promedio']], $r['por_categoria'])),
            new HojaReporteTic('Comentarios', ['Ticket', 'Fecha', 'Calificación', 'Técnico', 'Categoría', 'Comentario'],
                array_map(fn (array $c) => [
                    $c['folio'], LibroReporteTic::fecha($c['fecha'], true), $c['calificacion'], $c['tecnico'], $c['categoria'], $c['comentario'],
                ], $r['comentarios'])),
        ]);
    }

    /** @param  array<string, mixed>  $r  `ReporteConsumosService::generar` (fase 5.4d) */
    public static function consumos(array $r): LibroReporteTic
    {
        $grupo = ['impresora' => 'Impresora', 'area' => 'Área', 'consumible' => 'Consumible'][$r['agrupado_por']];
        $resumen = array_map(fn (array $g) => [$g['nombre'], $g['cantidad'], $g['entregas']], $r['grupos']);
        $resumen[] = ['Total', $r['cantidad'], $r['entregas']];
        $desglose = [];
        foreach ($r['grupos'] as $g) {
            foreach ($g['desglose'] as $d) {
                $desglose[] = [$g['nombre'], $d['nombre'], $d['cantidad']];
            }
        }

        return new LibroReporteTic([
            new HojaReporteTic("Por {$grupo}", [$grupo, 'Cantidad', 'Entregas'], $resumen),
            new HojaReporteTic('Desglose', [$grupo, $r['agrupado_por'] === 'consumible' ? 'Área' : 'Consumible', 'Cantidad'], $desglose),
            new HojaReporteTic('Entregas', [
                'Fecha', 'Consumible', 'Código de bodega', 'Tipo', 'Lo guarda', 'Cantidad', 'Equipo', 'Modelo', 'Área', 'Retiró', 'Ticket',
            ], array_map(fn (array $f) => [
                LibroReporteTic::fecha($f['fecha']), $f['consumible'], $f['codigo_bodega'], $f['tipo'], $f['guarda_tic'] ? 'TIC' : 'Bodega',
                $f['cantidad'], $f['equipo'], $f['equipo_modelo'], $f['unidad'], $f['retira'], $f['ticket'],
            ], $r['filas'])),
        ]);
    }

    /** @param  array<string, mixed>  $r  `ReporteSaldoConsumiblesService::generar` (fase 5.4d) */
    public static function saldoConsumibles(array $r): LibroReporteTic
    {
        return new LibroReporteTic([
            new HojaReporteTic('Saldo al ' . LibroReporteTic::fecha($r['fecha']), [
                'Código de bodega', 'Descripción', 'Tipo', 'Presentación', 'Saldo', 'Stock mínimo', 'Bajo el mínimo', 'Último movimiento', 'Activo',
            ], [
                ...array_map(fn (array $f) => [
                    $f['codigo_bodega'], $f['descripcion'], $f['tipo'], $f['presentacion'], $f['saldo'], $f['stock_minimo'] ?: '',
                    $f['bajo_minimo'] ? 'Sí' : '', LibroReporteTic::fecha($f['ultimo_movimiento']), $f['activo'] ? 'Sí' : 'No',
                ], $r['filas']),
                ['', 'Total', '', '', $r['unidades'], '', '', '', ''],
            ]),
        ]);
    }

    /**
     * El kárdex de un consumible entre dos fechas (fase 5.4d).
     *
     * @param  array<string, mixed>  $k  `MovimientoConsumibleService::kardex`
     */
    public static function kardex(array $k): LibroReporteTic
    {
        $filas = [];
        if ($k['saldo_inicial'] !== null) {
            $filas[] = ['', 'Saldo inicial', '', '', $k['saldo_inicial'], '', '', ''];
        }
        foreach ($k['filas'] as $m) {
            $filas[] = [
                LibroReporteTic::fecha($m['fecha']),
                ucfirst($m['tipo']),
                self::detalleMovimiento($m),
                $m['cantidad'],
                $m['saldo'],
                $m['registrado_por'],
                LibroReporteTic::fecha($m['registrado_en'], true),
                $m['anulado_en'] ? "Anulado por {$m['anulado_por']}: {$m['motivo_anulacion']}" : '',
            ];
        }

        return new LibroReporteTic([
            new HojaReporteTic('Kárdex', ['Fecha', 'Movimiento', 'Detalle', 'Cantidad', 'Saldo', 'Registró', 'Registrado', 'Anulación'], $filas),
        ]);
    }

    /** @param  array<string, mixed>  $m */
    private static function detalleMovimiento(array $m): string
    {
        return match ($m['tipo']) {
            'ingreso' => trim(DocumentoIngresoConsumible::from($m['tipo_documento'])->etiqueta() . ' ' . $m['numero_documento']),
            'entrega' => implode(' · ', array_filter([
                $m['equipo'] ? "{$m['equipo']['codigo_institucional']} {$m['equipo']['descripcion']}" : 'Sin equipo',
                $m['retira'], $m['unidad'], $m['ticket'],
            ])),
            'ajuste'  => trim(($m['motivo_ajuste'] ? MotivoAjusteConsumible::from($m['motivo_ajuste'])->etiqueta() . ': ' : '') . ($m['observacion'] ?? '')),
            default   => (string) ($m['observacion'] ?? ''),
        };
    }
}
