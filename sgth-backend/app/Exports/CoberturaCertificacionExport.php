<?php

namespace App\Exports;

use App\Enums\EstadoCoberturaCertificacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * El estado de las evaluaciones médicas ocupacionales de la plantilla.
 *
 * Es el papel que pide una auditoría del IESS o del Ministerio de Trabajo:
 * quién está al día, quién vencido y quién nunca fue evaluado.
 */
class CoberturaCertificacionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const CABECERAS = [
        'CÉDULA', 'SERVIDOR', 'UNIDAD ADMINISTRATIVA', 'CARGO',
        'ÚLTIMA EVALUACIÓN', 'APTITUD', 'RESTRICCIONES',
        'VENCE EL', 'ESTADO', 'SOLICITUD EN CURSO',
    ];

    private const DICTAMENES = [
        'apto' => 'Apto',
        'apto_con_restricciones' => 'Apto con restricciones',
        'no_apto' => 'No apto',
    ];

    private const ESTADOS_SOLICITUD = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En proceso',
    ];

    public function __construct(private readonly Collection $filas)
    {
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn ($fila) => [
            $fila->cedula,
            $fila->nombre_completo,
            $fila->unidad ?? 'Sin unidad asignada',
            $fila->cargo ?? 'Sin cargo asignado',
            $fila->fecha_evaluacion ?? 'Nunca',
            $fila->ultimo_dictamen ? (self::DICTAMENES[$fila->ultimo_dictamen] ?? $fila->ultimo_dictamen) : '—',
            $fila->restricciones ?? '—',
            $fila->vence_el ?? '—',
            EstadoCoberturaCertificacion::from($fila->estado_cobertura)->etiqueta(),
            $fila->solicitud_activa_estado
                ? (self::ESTADOS_SOLICITUD[$fila->solicitud_activa_estado] ?? $fila->solicitud_activa_estado)
                : 'No',
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
