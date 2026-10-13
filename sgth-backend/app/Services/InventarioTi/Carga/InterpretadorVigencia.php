<?php

namespace App\Services\InventarioTi\Carga;

use Carbon\Carbon;

/**
 * Lee el texto de la columna «MANTENIMIENTO / VIGENCIA TECNOLOGICA» del Excel
 * de TIC, que trae el contrato de vigencia escrito a mano:
 *
 *   «VIGENCIA TECNOLOGICA, PROCESO SIE-GADPE-018-2021; CRONOGRAMA DE
 *    MANTENIMIENTO Entre el 15 y 30 de octubre del 2022 / 2023 / 2024; …
 *    ADMINISTRADOR: Jean Loor.»
 *   «VIGENCIA TECNOLOGICA 1.- JULIO - 2024; 2.- JULIO - 2025; 3.- JULIO - 2026;
 *    Administrador: Mina Quintero Jose Luis»
 *
 * Saca el proceso, el administrador y las ventanas de mantenimiento. Lo que
 * no entiende se queda en las observaciones del contrato, que TIC revisa.
 */
final class InterpretadorVigencia
{
    private const MESES = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7,
        'AGOSTO' => 8, 'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];

    /**
     * @return array{clave: string, proceso: ?string, administrador: ?string, cronograma: list<array{fecha_desde: string, fecha_hasta: string, descripcion: string}>, texto: string}|null
     */
    public function interpretar(?string $texto): ?array
    {
        $original = Normaliza::celda($texto);
        $normal = Normaliza::texto($original);
        // «VT- ADMINISTRADOR CARLOS BATIOJA» es la misma vigencia, abreviada.
        if ($original === null || ! (str_contains($normal, 'VIGENCIA') || preg_match('/^VT\b/', $normal))) {
            return null;
        }

        $proceso = preg_match('/\b([A-Z]{2,6}-GADPE-\d{2,4}-\d{4})\b/', $normal, $m) ? $m[1] : null;

        return [
            // Un mismo contrato es el mismo proceso; sin proceso, el mismo texto.
            'clave'         => $proceso ?? $normal,
            'proceso'       => $proceso,
            'administrador' => $this->administrador($normal),
            'cronograma'    => [...$this->porMeses($normal), ...$this->porDias($normal)],
            'texto'         => $original,
        ];
    }

    private function administrador(string $normal): ?string
    {
        if (preg_match('/(?:ADMINISTRADOR|RESPONSABLE)\s*:?\s*([A-Z ]{5,}?)\s*(?:\.|;|,|$)/', $normal, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** «1.- JULIO - 2024; 2.- JULIO - 2025»: el mes entero de cada año. */
    private function porMeses(string $normal): array
    {
        preg_match_all('/\d\s*\.-\s*([A-Z]+)\s*-\s*(\d{4})/', $normal, $m, PREG_SET_ORDER);

        return array_values(array_filter(array_map(function (array $c) {
            $mes = self::MESES[$c[1]] ?? null;
            if (! $mes) {
                return null;
            }
            $inicio = Carbon::create((int) $c[2], $mes, 1);

            return ['fecha_desde' => $inicio->toDateString(), 'fecha_hasta' => $inicio->copy()->endOfMonth()->toDateString(), 'descripcion' => 'Del Excel de TIC'];
        }, $m)));
    }

    /** «Entre el 15 y 30 de octubre del 2022 / 2023 / 2024»: los mismos días de cada año. */
    private function porDias(string $normal): array
    {
        if (! preg_match('/ENTRE EL (\d{1,2}) Y (?:EL )?(\d{1,2}) DE ([A-Z]+) DEL? ((?:\d{4}\s*\/?\s*)+)/', $normal, $m)) {
            return [];
        }

        $mes = self::MESES[$m[3]] ?? null;
        preg_match_all('/\d{4}/', $m[4], $anios);
        if (! $mes) {
            return [];
        }

        return array_map(fn (string $anio) => [
            'fecha_desde' => Carbon::create((int) $anio, $mes, (int) $m[1])->toDateString(),
            'fecha_hasta' => Carbon::create((int) $anio, $mes, (int) $m[2])->toDateString(),
            'descripcion' => 'Del Excel de TIC',
        ], $anios[0]);
    }
}
