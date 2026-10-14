<?php

namespace App\Exports\Tic;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Un reporte de TIC en Excel, con una hoja o varias (fase 4.2). Cada reporte
 * arma sus hojas con `ReportesTicExcel`.
 */
final class LibroReporteTic implements WithMultipleSheets
{
    /** @param  list<HojaReporteTic>  $hojas */
    public function __construct(private readonly array $hojas) {}

    public function sheets(): array
    {
        return $this->hojas;
    }

    /** `2026-10-14` → `14/10/2026`; sin fecha, vacío. */
    public static function fecha(?string $iso, bool $conHora = false): string
    {
        if (! $iso) {
            return '';
        }

        return Carbon::parse($iso)->setTimezone(config('app.timezone'))->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
