<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * El consolidado de permisos en Excel (y el ausentismo por enfermedad de
 * Riesgos Laborales, que es el mismo informe).
 *
 * Era un CSV con separador «;» y cabeceras sin tildes («Cedula», «Total
 * Dias»), sin título ni período: abierto en Excel, la cédula perdía el cero y
 * nada decía de qué mes eran las cifras. Ahora (2026-11-24), en el estilo de
 * los documentos institucionales:
 *
 * - Título y, debajo, el período, el alcance y cuándo se generó, combinados
 *   sobre toda la tabla.
 * - La cédula como texto: si no, Excel lee «0812345607» como un número.
 * - Las horas como duración (`[h]:mm`) y los días con dos decimales, calculados
 *   de los minutos con fórmula, y los totales con SUM: se pueden filtrar y
 *   seguir sumando.
 * - Filtro en los encabezados y la tabla fija al desplazarse.
 *
 * Las filas de datos empiezan en la 5: 1 título, 2 subtítulo, 3 en blanco
 * —que se escribe `['']`, porque una fila `[]` no se escribe y corre todo lo
 * de abajo—, 4 encabezados.
 */
final class ConsolidadoPermisosExport extends DefaultValueBinder implements FromArray, WithTitle, WithStrictNullComparison, WithEvents, WithCustomValueBinder
{
    private const PRIMERA = 5;
    private const VERDE = '17593F';
    private const BORDE = 'C6D2CC';
    private const SUPERFICIE = 'F2F6F4';

    /**
     * @param  list<array{cedula: string, nombre: string, unidad: string, total_permisos: int, total_minutos: int}>  $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $rotuloCuenta,
        private readonly string $hoja,
        private readonly array $filas,
    ) {}

    public function array(): array
    {
        $datos = [
            [$this->titulo],
            [$this->subtitulo],
            [''],
            ['N.º', 'Cédula', 'Servidor', 'Unidad administrativa', $this->rotuloCuenta, 'Minutos', 'Horas', 'Días'],
        ];

        if ($this->filas === []) {
            $datos[] = ['Sin registros en el período.'];

            return $datos;
        }

        foreach (array_values($this->filas) as $i => $fila) {
            $r = self::PRIMERA + $i;
            $datos[] = [
                $i + 1,
                $fila['cedula'],
                $fila['nombre'],
                $fila['unidad'],
                $fila['total_permisos'],
                $fila['total_minutos'],
                "=F{$r}/1440",
                "=F{$r}/480",
            ];
        }

        $ultima = $this->ultimaFila();
        $datos[] = ['Total', '', '', '', ...array_map(
            fn (string $c) => "=SUM({$c}" . self::PRIMERA . ":{$c}{$ultima})",
            ['E', 'F', 'G', 'H'],
        )];

        return $datos;
    }

    public function title(): string
    {
        return $this->hoja;
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'B' && $cell->getRow() >= self::PRIMERA && $value !== null && $value !== '') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                $hoja = $evento->sheet->getDelegate();

                $hoja->mergeCells('A1:H1');
                $hoja->mergeCells('A2:H2');
                $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::VERDE);
                $hoja->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('5B6B63');

                foreach (['A' => 6, 'B' => 13, 'C' => 40, 'D' => 46, 'E' => 11, 'F' => 10, 'G' => 9, 'H' => 9] as $columna => $ancho) {
                    $hoja->getColumnDimension($columna)->setWidth($ancho);
                }

                $hoja->getStyle('A4:H4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '3D4D46']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SUPERFICIE]],
                ]);
                $hoja->getStyle('E4:H4')->getAlignment()->setHorizontal('right');

                if ($this->filas === []) {
                    $hoja->mergeCells('A5:H5');
                    $hoja->getStyle('A5')->getFont()->setItalic(true)->getColor()->setRGB('5B6B63');

                    return;
                }

                $ultima = $this->ultimaFila();
                $total = $ultima + 1;

                $hoja->getStyle("A4:H{$total}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDE]]],
                ]);
                // Después del gris, para que no la pise.
                $hoja->getStyle('A4:H4')->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::VERDE);

                $hoja->mergeCells("A{$total}:D{$total}");
                $hoja->getStyle("A{$total}:H{$total}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SUPERFICIE]],
                ]);
                $hoja->getStyle("A{$total}")->getAlignment()->setHorizontal('right');

                $primera = self::PRIMERA;
                $hoja->getStyle("G{$primera}:G{$total}")->getNumberFormat()->setFormatCode('[h]:mm');
                $hoja->getStyle("H{$primera}:H{$total}")->getNumberFormat()->setFormatCode('0.00');

                $hoja->freezePane("A{$primera}");
                $hoja->setAutoFilter("A4:H{$ultima}");

                $hoja->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setRowsToRepeatAtTopByStartAndEnd(4, 4);
            },
        ];
    }

    private function ultimaFila(): int
    {
        return self::PRIMERA + count($this->filas) - 1;
    }
}
