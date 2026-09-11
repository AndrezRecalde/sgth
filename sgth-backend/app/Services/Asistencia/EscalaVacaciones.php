<?php

namespace App\Services\Asistencia;

use App\Enums\RegimenLaboral;

/**
 * Cuántos días de vacaciones genera un año de servicio, según el régimen.
 *
 * Confirmado con Talento Humano el 2026-09-11:
 *
 * - LOSEP, art. 29: treinta días calendario al año, sin escala por
 *   antigüedad, completos desde el primer año. El sistema aplicaba una escala
 *   15/20/25/30 que la ley no contiene: un servidor nuevo recibía la mitad.
 * - Código del Trabajo, art. 69: quince días, y uno más por cada año que
 *   exceda de cinco con el mismo empleador, hasta quince adicionales. No hay
 *   contrato colectivo que conceda más. Los períodos sumaban el día adicional
 *   desde el segundo año.
 * - Servicios profesionales: contrato civil, no genera vacaciones.
 *
 * Antes la escala vivía en dos sitios —los períodos y los motores— con
 * fórmulas distintas. Aquí queda una sola, y los dos la consultan.
 */
final class EscalaVacaciones
{
    /** LOSEP, art. 29. */
    public const DIAS_LOSEP = 30.0;

    /** Código del Trabajo, art. 69: la base. */
    public const DIAS_BASE_CODIGO_TRABAJO = 15.0;

    /** Años con el mismo empleador tras los que empieza el día adicional. */
    public const ANIOS_SIN_ADICIONAL = 5;

    /** Tope de días adicionales por antigüedad (art. 69). */
    public const MAX_DIAS_ADICIONALES = 15;

    /**
     * Días que genera un año de servicio.
     *
     * @param  string  $regimen          valor de `RegimenLaboral`
     * @param  int     $aniosCompletos   años completos de servicio: en LOSEP no
     *                                   cambian nada; en el Código del Trabajo,
     *                                   con el empleador actual
     */
    public static function diasGenerados(string $regimen, int $aniosCompletos): float
    {
        return match ($regimen) {
            RegimenLaboral::SERVICIOS_PROFESIONALES->value => 0.0,
            RegimenLaboral::CODIGO_TRABAJO->value => self::DIAS_BASE_CODIGO_TRABAJO
                + min(self::MAX_DIAS_ADICIONALES, max(0, $aniosCompletos - self::ANIOS_SIN_ADICIONAL)),
            default => self::DIAS_LOSEP,
        };
    }
}
