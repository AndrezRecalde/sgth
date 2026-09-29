<?php

namespace App\Services\Asistencia;

/**
 * La jornada de ocho horas, y cómo se pasa de horas a días de vacaciones.
 *
 * El 480 vivía en tres sitios con tres redondeos: `PermisoService`, el resumen
 * de períodos y el tablero de SSO. El del resumen no era una copia inocente
 * —era el único que además reimplementaba la resta de horas—, y de ahí venía
 * que un permiso rechazado apareciera como días descontados.
 *
 * Aquí queda una sola jornada y una sola forma de restar dos horas. El
 * redondeo sigue siendo de cada quien: un permiso se guarda con cuatro
 * decimales porque cuatro horas son 0.5 días y treinta minutos 0.0625, y un
 * consolidado se presenta con dos.
 */
final class JornadaLaboral
{
    /** Minutos de una jornada completa: ocho horas. */
    public const MINUTOS = 480;

    /**
     * Minutos entre dos horas del mismo día.
     *
     * Acepta `"08:30:00"` y `"08:30"`, que son las dos formas en que las horas
     * llegan desde la base de datos según cómo se haya leído la columna.
     */
    public static function minutosEntre(string $inicio, string $fin): int
    {
        return self::aMinutos($fin) - self::aMinutos($inicio);
    }

    /** `"08:30:00"` o `"08:30"` → 510. */
    public static function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)) + [1 => '0']);

        return $h * 60 + $m;
    }

    /** Minutos a días de vacaciones, con los decimales que pida quien llama. */
    public static function aDias(int|float $minutos, int $decimales = 4): float
    {
        return round($minutos / self::MINUTOS, $decimales);
    }
}
