<?php

namespace App\Exports;

use App\Models\Nomina\Nomina;
use App\Services\Expediente\OcupantesDelPuesto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * La nómina del SGTH por servidor, para compararla con la de Financiero en la
 * corrida en paralelo (fase 7.3c): los días, la remuneración y cada concepto
 * en su columna. Las cuotas de préstamos y los demás descuentos van juntos.
 */
class NominaComparacionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private const COLUMNAS = [
        'SUELDO_BASE'               => 'SUELDO',
        'RETROACTIVO'               => 'RETROACTIVO',
        'SUBROGACION'               => 'SUBROGACIÓN',
        'ENCARGO'                   => 'ENCARGO',
        'REMUNERACIONES_PENDIENTES' => 'REMUNERACIONES PENDIENTES',
        'DECIMO_TERCERO'            => 'DÉCIMO TERCERO',
        'DECIMO_CUARTO'             => 'DÉCIMO CUARTO',
        'DIAS_SIN_REMUNERACION'     => 'DÍAS SIN REMUNERACIÓN',
        'MULTA_DISCIPLINARIA'       => 'MULTA',
        'IESS_PERSONAL'             => 'IESS PERSONAL',
    ];

    public function __construct(private readonly Nomina $nomina)
    {
    }

    public function collection(): Collection
    {
        $nomina = $this->nomina->load([
            'rolesPago.servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido,regimen_laboral',
            'detalles.concepto:id,codigo,tipo',
        ]);
        $detalles = $nomina->detalles->groupBy('servidor_id');

        return $nomina->rolesPago
            ->sortBy(fn ($rol) => OcupantesDelPuesto::nombre($rol->servidor))
            ->values()
            ->map(function ($rol) use ($detalles) {
                $deEste = $detalles->get($rol->servidor_id, collect());
                $porConcepto = fn (string $codigo) => round((float) $deEste->where('concepto.codigo', $codigo)->sum('valor'), 2);
                $otros = round((float) $deEste
                    ->filter(fn ($d) => $d->concepto?->tipo?->value === 'descuento' && ! array_key_exists($d->concepto->codigo, self::COLUMNAS))
                    ->sum('valor'), 2);

                return [
                    $rol->servidor?->cedula,
                    OcupantesDelPuesto::nombre($rol->servidor),
                    $rol->dias,
                    $rol->remuneracion !== null ? (float) $rol->remuneracion : null,
                    ...array_map($porConcepto, array_keys(self::COLUMNAS)),
                    $otros,
                    (float) $rol->total_ingresos,
                    (float) $rol->total_descuentos,
                    (float) $rol->total_neto,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'CÉDULA', 'SERVIDOR', 'DÍAS', 'REMUNERACIÓN',
            ...array_values(self::COLUMNAS),
            'OTROS DESCUENTOS', 'TOTAL INGRESOS', 'TOTAL DESCUENTOS', 'NETO',
        ];
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
