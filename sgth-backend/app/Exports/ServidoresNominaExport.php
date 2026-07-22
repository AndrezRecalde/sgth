<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServidoresNominaExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const CABECERAS = [
        'ITEM', 'CÉDULA', 'NOMBRES Y APELLIDOS', 'GENERO', 'ESTADO CIVIL',
        'TIPO DE DISCAPACIDAD', 'PORCENTAJE', 'CARGO', 'GRUPO OCUPACIONAL',
        'R.M.U', 'R.A.U', 'TIPO DE NOMBRAMIENTO', 'GESTIÓN', 'FORMACIÓN',
        'FECHA DE INGRESO', 'FECHA DE SALIDA', 'FECHA DE NACIMIENTO', 'EDAD',
        'DIRECCIÓN', 'CELULAR', 'CORREO PERSONAL', 'CORREO INSTITUCIONAL',
    ];

    public function __construct(private readonly Collection $datos)
    {
    }

    public function collection(): Collection
    {
        return $this->datos->map(fn (array $fila) => array_map(
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
