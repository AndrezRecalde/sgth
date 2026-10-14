<?php

namespace App\Exports\Tic;

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
}
