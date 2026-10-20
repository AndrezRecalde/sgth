<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Las novedades para nómina de un mes, para Financiero (fase 7.3a): una fila
 * por novedad o ajuste, con el acto que la respalda.
 */
class NovedadesNominaExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const CABECERAS = [
        'MES', 'TIPO', 'CÉDULA', 'SERVIDOR', 'NOVEDAD', 'DETALLE', 'DESDE', 'HASTA', 'DÍAS', 'ACTO',
    ];

    /** @param  Collection<int, array<string, mixed>>  $filas */
    public function __construct(private readonly Collection $filas)
    {
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn (array $fila) => array_map(
            fn (string $cabecera) => $fila[$cabecera] ?? null,
            self::CABECERAS
        ));
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
