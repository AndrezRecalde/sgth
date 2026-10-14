<?php

namespace App\Exports\Tic;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Una hoja de los reportes de TIC (fase 4.2): título, encabezados en negrita y
 * filas. `WithStrictNullComparison`: sin él, un 0 se escribe como celda vacía.
 */
final class HojaReporteTic implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison, WithStyles
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->encabezados;
    }

    /** Excel no admite más de 31 caracteres ni algunos signos en el nombre de una hoja. */
    public function title(): string
    {
        return mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $this->titulo), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
