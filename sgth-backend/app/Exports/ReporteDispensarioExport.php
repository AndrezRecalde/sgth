<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Un reporte del Dispensario en Excel, con su encabezado: qué es, de qué
 * período, de qué alcance y quién lo sacó. Sin eso, una hoja que circula
 * impresa no dice si son todas las atenciones o solo las de un médico.
 */
class ReporteDispensarioExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** La fila de los títulos de columna, después del encabezado. */
    private const FILA_CABECERA = 6;

    /**
     * @param list<array{clave: string, titulo: string}> $columnas
     * @param list<array<string, scalar|null>> $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly string $periodo,
        private readonly string $alcance,
        private readonly string $generadoPor,
        private readonly array $columnas,
        private readonly array $filas,
    ) {}

    public function array(): array
    {
        return [
            ['GAD Provincial de Esmeraldas — Dispensario Médico'],
            [$this->titulo],
            ["Período: {$this->periodo}  ·  {$this->alcance}"],
            ['Generado por ' . $this->generadoPor . ' el ' . now()->format('d/m/Y H:i')],
            // Una fila vacía de verdad: con `[]` el exportador la omite y la
            // cabecera sube un renglón.
            [''],
            array_column($this->columnas, 'titulo'),
            ...array_map(
                fn (array $fila) => array_map(fn ($c) => $fila[$c['clave']] ?? null, $this->columnas),
                $this->filas,
            ),
        ];
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            2                   => ['font' => ['bold' => true, 'size' => 13]],
            self::FILA_CABECERA => ['font' => ['bold' => true]],
        ];
    }
}
