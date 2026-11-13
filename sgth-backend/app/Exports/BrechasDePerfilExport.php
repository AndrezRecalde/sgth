<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Las brechas de perfil en Excel (docs/diseno-manual-de-puestos.md, P7,
 * PR 17), para armar el plan de capacitación: una fila por servidor con lo
 * que tiene y lo que pide el perfil de su puesto.
 *
 * Con `WithStrictNullComparison`: sin él, un 0 (ningún título por validar)
 * salía como celda vacía.
 */
class BrechasDePerfilExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
    private const CABECERAS = [
        'CÉDULA', 'SERVIDOR', 'UNIDAD ADMINISTRATIVA', 'PUESTO', 'GRADO',
        'INSTRUCCIÓN QUE PIDE EL PUESTO', 'TÍTULO VALIDADO MÁS ALTO', 'INSTRUCCIÓN', 'TÍTULOS POR VALIDAR',
        'CAPACITACIÓN QUE PIDE EL PUESTO', 'CURSOS VALIDADOS', 'CURSOS RELACIONADOS',
    ];

    public const ESTADOS = [
        'cumple'         => 'Cumple',
        'no_cumple'      => 'No cumple',
        'por_revisar'    => 'Por revisar',
        'sin_requisitos' => 'El puesto no tiene requisitos',
    ];

    /** @param  Collection<int, array<string, mixed>>  $filas */
    public function __construct(private readonly Collection $filas)
    {
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn (array $f) => [
            $f['servidor']['cedula'],
            $f['servidor']['nombre'],
            $f['puesto']['unidad'] ?? '—',
            $f['puesto']['cargo'] ?? '—',
            $f['puesto']['grado'] ?? '—',
            $f['instruccion']['pide'] ? implode(' o ', $f['instruccion']['pide']) : '—',
            $f['instruccion']['tiene'] ?? 'Ninguno',
            self::ESTADOS[$f['instruccion']['estado']] ?? $f['instruccion']['estado'],
            $f['instruccion']['por_validar'],
            $f['capacitacion']['tematicas'] ?? '—',
            $f['capacitacion']['registradas'],
            $f['capacitacion']['relacionadas']
                ? implode('; ', array_column($f['capacitacion']['relacionadas'], 'titulo'))
                : '—',
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
