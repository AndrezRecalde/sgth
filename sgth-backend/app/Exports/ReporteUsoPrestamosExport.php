<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * El reporte de uso de los equipos de préstamo (fase 2.4), en una hoja.
 * `WithStrictNullComparison`: sin él, un 0 se escribe como celda vacía.
 */
final class ReporteUsoPrestamosExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    /** @param  array{desde: string, hasta: string, equipos: list<array<string, mixed>>, totales: array<string, float|int>}  $reporte */
    public function __construct(private readonly array $reporte) {}

    public function array(): array
    {
        $filas = array_map(fn (array $e) => [
            $e['codigo_institucional'],
            trim(implode(' ', array_filter([$e['tipo'], $e['marca'], $e['modelo']]))),
            $e['prestable'] ? 'Sí' : 'No',
            $e['prestamos'],
            $e['horas'],
            $e['devoluciones_tarde'],
            $e['no_retirados'],
            $e['ultimo_prestamo'] ? Carbon::parse($e['ultimo_prestamo'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i') : '',
        ], $this->reporte['equipos']);

        $t = $this->reporte['totales'];
        $filas[] = ['Total', '', '', $t['prestamos'], $t['horas'], $t['devoluciones_tarde'], $t['no_retirados'], ''];

        return $filas;
    }

    public function headings(): array
    {
        return ['Código', 'Equipo', 'En el grupo de préstamo', 'Préstamos', 'Horas fuera', 'Devueltos tarde', 'Reservas no retiradas', 'Último préstamo'];
    }

    public function title(): string
    {
        return "Uso {$this->reporte['desde']} a {$this->reporte['hasta']}";
    }
}
