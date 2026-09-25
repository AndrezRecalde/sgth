<?php

namespace App\Services\Sso;

use App\Exceptions\ReglaNegocioException;
use Illuminate\Support\Carbon;

/**
 * El período con el que trabaja todo el módulo SSO: un año ('2026') o un mes
 * ('2026-07').
 *
 * Existe porque la misma idea estaba escrita en nueve sitios: la expresión
 * regular a mano en siete controladores y el paso de período a rango de fechas
 * copiado en `SsoService` y en `DashboardSsoService`, idéntico y con su propia
 * excepción. Dos copias de una regla son dos oportunidades de que una cambie.
 *
 * De paso arregla lo que las copias compartían: la expresión aceptaba
 * `2026-13`, y `Carbon::createFromFormat` lo desbordaba en silencio a enero de
 * 2027. Ahora el mes tiene que estar entre 01 y 12.
 */
final class PeriodoSso
{
    /** Año de cuatro cifras, con mes opcional de 01 a 12. */
    public const PATRON = '^\d{4}(-(0[1-9]|1[0-2]))?$';

    /** Reglas de validación para un período obligatorio. */
    public static function reglas(): array
    {
        return ['required', 'string', 'regex:/'.self::PATRON.'/'];
    }

    /** Las mismas, cuando el período puede no venir. */
    public static function reglasOpcionales(): array
    {
        return ['nullable', 'string', 'regex:/'.self::PATRON.'/'];
    }

    public static function esValido(string $periodo): bool
    {
        return (bool) preg_match('/'.self::PATRON.'/', $periodo);
    }

    public static function esAnio(string $periodo): bool
    {
        return (bool) preg_match('/^\d{4}$/', $periodo);
    }

    /**
     * El rango de fechas que cubre el período, para comparar contra columnas
     * de fecha (los accidentes, las inspecciones, los permisos).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rango(string $periodo): array
    {
        if (self::esAnio($periodo)) {
            $inicio = Carbon::createFromDate((int) $periodo, 1, 1)->startOfYear();

            return [$inicio, $inicio->copy()->endOfYear()];
        }

        if (self::esValido($periodo)) {
            $inicio = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();

            return [$inicio, $inicio->copy()->endOfMonth()];
        }

        throw new ReglaNegocioException('El período debe tener el formato AAAA o AAAA-MM.');
    }
}
