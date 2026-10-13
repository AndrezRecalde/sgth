<?php

namespace App\Services\InventarioTi\Carga;

use Illuminate\Support\Str;

/**
 * Cómo se comparan los textos del Excel de TIC con los del sistema: sin
 * tildes, en mayúsculas y con un solo espacio. «Mero Bermeo  Gabriela» y
 * «MERO BERMEO GABRIELA» son la misma persona; «DELL » y «Dell», la misma marca.
 */
final class Normaliza
{
    public static function texto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = Str::upper(Str::ascii(trim((string) $valor)));

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    /** El valor de una celda como texto limpio, o null si está vacía o dice «S/N». */
    public static function celda(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');

        return $texto === '' || in_array(self::texto($texto), ['S/N', 'SN', 'N/A', '-'], true) ? null : $texto;
    }

    /** Un número de soporte: los enteros de cinco cifras del Excel. */
    public static function soporte(mixed $valor): ?string
    {
        if (is_int($valor) || (is_float($valor) && floor($valor) === $valor)) {
            return (string) (int) $valor;
        }

        $texto = self::celda($valor);

        return $texto !== null && preg_match('/^\d{3,7}$/', $texto) ? $texto : null;
    }
}
