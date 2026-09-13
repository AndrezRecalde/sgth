<?php

namespace App\Support;

/**
 * Validación del RUC ecuatoriano.
 *
 * Trece dígitos: provincia (01–24, o 30 para los emitidos en el exterior),
 * el tercer dígito dice el tipo de contribuyente y cada tipo tiene su dígito
 * verificador:
 *
 * - 0–5, persona natural: la cédula (módulo 10) seguida de un establecimiento.
 * - 6, entidad pública: módulo 11 sobre los 8 primeros, verificador en el 9.º.
 * - 9, sociedad privada: módulo 11 sobre los 9 primeros, verificador en el 10.º.
 *
 * Solo sirve para avisar. El SRI dejó de garantizar el dígito verificador en
 * algunos RUC de sociedades nuevas, así que un RUC real puede no pasar: por eso
 * la liquidación no se bloquea con esto, se le muestra a Financiero.
 */
final class RucEcuador
{
    public static function esValido(?string $ruc): bool
    {
        if ($ruc === null || ! preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }

        $provincia = (int) substr($ruc, 0, 2);
        if (! (($provincia >= 1 && $provincia <= 24) || $provincia === 30)) {
            return false;
        }

        $digitos = array_map('intval', str_split($ruc));
        $tercero = $digitos[2];

        return match (true) {
            $tercero <= 5  => substr($ruc, 10) !== '000' && self::modulo10($digitos),
            $tercero === 6 => substr($ruc, 9) !== '0000' && self::modulo11($digitos, [3, 2, 7, 6, 5, 4, 3, 2], 8),
            $tercero === 9 => substr($ruc, 10) !== '000' && self::modulo11($digitos, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9),
            default        => false,
        };
    }

    /** Cédula: coeficientes 2,1,2,1…; los productos mayores a 9 restan 9. */
    private static function modulo10(array $d): bool
    {
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $producto = $d[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $producto > 9 ? $producto - 9 : $producto;
        }

        $verificador = (10 - $suma % 10) % 10;

        return $verificador === $d[9];
    }

    /** @param list<int> $coeficientes */
    private static function modulo11(array $d, array $coeficientes, int $posicion): bool
    {
        $suma = 0;
        foreach ($coeficientes as $i => $coeficiente) {
            $suma += $d[$i] * $coeficiente;
        }

        $resto = $suma % 11;
        $verificador = $resto === 0 ? 0 : 11 - $resto;

        return $verificador === $d[$posicion];
    }
}
