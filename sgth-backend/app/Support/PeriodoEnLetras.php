<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Un rango de fechas en letras, para los encabezados de los informes:
 * «del 1 al 30 de noviembre de 2026», con el mes nombrado dos veces si el
 * rango lo cruza («del 1 de noviembre al 31 de diciembre de 2026») y el año
 * también si cruza el año. La misma regla que el comprobante del viático.
 */
final class PeriodoEnLetras
{
    public static function de(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        $hastaEnLetras = $hasta->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $inicio = match (true) {
            $desde->year !== $hasta->year => $desde->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            $desde->month !== $hasta->month => $desde->locale('es')->isoFormat('D [de] MMMM'),
            default => (string) $desde->day,
        };

        return $desde->isSameDay($hasta) ? "del {$hastaEnLetras}" : "del {$inicio} al {$hastaEnLetras}";
    }
}
