<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Los actos para transcribir al SIITH o al SUT (fase 7.5), en el orden en que
 * se registraron, con una columna por dato del formulario.
 */
class ReporteSiithSutExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const CABECERAS = [
        'NÚMERO', 'SITUACIÓN', 'FECHA DE REGISTRO', 'RIGE DESDE', 'CLASE', 'CAUSAL', 'CÉDULA', 'SERVIDOR', 'MODALIDAD',
        'PUESTO DE ORIGEN', 'GRADO DE ORIGEN', 'UNIDAD DE ORIGEN', 'REMUNERACIÓN DE ORIGEN',
        'PUESTO DE DESTINO', 'GRADO DE DESTINO', 'UNIDAD DE DESTINO', 'REMUNERACIÓN DE DESTINO',
        'BASE LEGAL', 'DOCUMENTO',
    ];

    /** @param  Collection<int, array<string, mixed>>  $filas */
    public function __construct(private readonly Collection $filas)
    {
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn (array $fila) => array_map(fn (string $c) => $fila[$c] ?? null, self::CABECERAS));
    }

    public function headings(): array
    {
        return self::CABECERAS;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0D6EFD']],
            ],
        ];
    }
}
