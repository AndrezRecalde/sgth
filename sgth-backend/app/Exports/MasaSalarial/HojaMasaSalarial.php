<?php

namespace App\Exports\MasaSalarial;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Una hoja del Excel de la masa salarial. Los montos van como números, con
 * separador de miles, para que se puedan sumar; el porcentaje, con un decimal.
 * `WithStrictNullComparison`: sin él, un 0 se escribe como celda vacía.
 *
 * Las primeras filas pueden ser un encabezado —el título, el aviso, los
 * filtros— que ocupa todo el ancho de la tabla: van combinadas, y así el
 * ajuste automático de columnas no las toma en cuenta y la columna A no se
 * estira hasta el largo del aviso.
 *
 * Las columnas de texto —la cédula— se escriben como texto: si no, Excel lee
 * «0812345607» como un número y se come el cero.
 */
final class HojaMasaSalarial extends DefaultValueBinder implements FromArray, WithTitle, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithColumnFormatting, WithEvents, WithCustomValueBinder
{
    /**
     * @param  list<list<mixed>>  $filas
     * @param  list<int>  $negritas  las filas en negrita, desde 1
     * @param  list<string>  $dinero  las columnas con montos: 'C', 'D'…
     * @param  list<string>  $porcentaje  las columnas con porcentajes
     * @param  int  $encabezado  cuántas filas del principio van combinadas
     * @param  list<string>  $texto  las columnas que se escriben como texto
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $filas,
        private readonly array $negritas = [],
        private readonly array $dinero = [],
        private readonly array $porcentaje = [],
        private readonly int $encabezado = 0,
        private readonly array $texto = [],
    ) {
    }

    public function array(): array
    {
        return $this->filas;
    }

    public function title(): string
    {
        return $this->titulo;
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($value !== null && $value !== '' && in_array($cell->getColumn(), $this->texto, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return array_fill_keys($this->negritas, ['font' => ['bold' => true]]);
    }

    public function columnFormats(): array
    {
        return [
            ...array_fill_keys($this->dinero, NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1),
            ...array_fill_keys($this->porcentaje, '0.0'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                if ($this->encabezado === 0) {
                    return;
                }

                $hoja = $evento->sheet->getDelegate();
                $ultima = Coordinate::stringFromColumnIndex(max(1, ...array_map('count', $this->filas)));

                for ($fila = 1; $fila <= $this->encabezado; $fila++) {
                    $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
                    $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true)->setVertical('top');
                }

                // El aviso es largo: combinado, Excel no calcula su alto.
                $hoja->getRowDimension(2)->setRowHeight(45);
                $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            },
        ];
    }
}
