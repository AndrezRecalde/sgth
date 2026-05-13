<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteGenericoExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private array $datos;
    private array $cabeceras;

    public function __construct(array $datos)
    {
        $this->datos = $datos;
        
        // Auto-generar cabeceras basadas en las llaves del primer registro
        $this->cabeceras = !empty($datos) ? array_keys((array) $datos[0]) : [];
    }

    public function array(): array
    {
        return $this->datos;
    }

    public function headings(): array
    {
        // Capitalizar cabeceras
        return array_map(function($heading) {
            return strtoupper(str_replace('_', ' ', $heading));
        }, $this->cabeceras);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la primera fila (Cabeceras)
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0D6EFD']]],
        ];
    }
}
