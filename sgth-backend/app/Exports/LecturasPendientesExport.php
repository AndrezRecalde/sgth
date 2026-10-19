<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Quién no confirmó —o no abrió— una publicación de Talento Humano
 * (docs/diseno-noticias-comunicados.md, 6.4). Es la lista que TH reparte a
 * cada dirección para insistir; va ordenada por unidad.
 */
class LecturasPendientesExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const CABECERAS = ['CÉDULA', 'SERVIDOR', 'UNIDAD ADMINISTRATIVA', 'ESTADO', 'LEÍDO EL'];

    private const ESTADOS = [
        'sin_leer' => 'Sin leer',
        'leido'    => 'Leído, sin confirmar',
    ];

    /** @param  Collection<int, array<string, mixed>>  $filas */
    public function __construct(private readonly Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas->map(fn (array $fila) => [
            $fila['cedula'] ?? '',
            $fila['nombre'],
            $fila['unidad'] ?? 'Sin unidad',
            self::ESTADOS[$fila['estado']] ?? $fila['estado'],
            $fila['leido_at'] ? Carbon::parse($fila['leido_at'])->format('d/m/Y H:i') : '',
        ]);
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
