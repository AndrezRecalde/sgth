<?php
namespace App\Helpers;

class NumeroALetras
{
    private static array $unidades = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO',
        'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
        'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    private static array $decenas = [
        '', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA',
        'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private static array $centenas = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS',
        'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS',
        'NOVECIENTOS',
    ];

    public static function convertir(float $numero): string
    {
        $numero   = round($numero, 2);
        $entero   = (int) $numero;
        $decimales = round(($numero - $entero) * 100);

        $letras = self::enLetras($entero);

        if ($decimales > 0) {
            $letras .= ' CON ' . sprintf('%02d', $decimales) . '/100';
        }

        return trim($letras) . ' DÓLARES';
    }

    private static function enLetras(int $numero): string
    {
        if ($numero === 0) return 'CERO';
        if ($numero < 0)   return 'MENOS ' . self::enLetras(-$numero);

        $letras = '';

        if ($numero >= 1000000) {
            $millones = (int) ($numero / 1000000);
            $letras  .= ($millones === 1 ? 'UN MILLÓN' : self::enLetras($millones) . ' MILLONES');
            $numero  %= 1000000;
            if ($numero > 0) $letras .= ' ';
        }

        if ($numero >= 1000) {
            $miles   = (int) ($numero / 1000);
            $letras .= ($miles === 1 ? 'MIL' : self::enLetras($miles) . ' MIL');
            $numero %= 1000;
            if ($numero > 0) $letras .= ' ';
        }

        if ($numero >= 100) {
            $c = (int) ($numero / 100);
            if ($numero === 100) {
                $letras .= 'CIEN';
            } else {
                $letras .= self::$centenas[$c];
            }
            $numero %= 100;
            if ($numero > 0) $letras .= ' ';
        }

        if ($numero >= 20) {
            $d = (int) ($numero / 10);
            $letras .= self::$decenas[$d];
            $numero %= 10;
            if ($numero > 0) $letras .= ' Y ';
        }

        if ($numero > 0) {
            $letras .= self::$unidades[$numero];
        }

        return $letras;
    }
}
