<?php

namespace App\Enums;

use App\Enums\RegimenLaboral;

/**
 * Qué documento corresponde según el régimen de la persona.
 *
 * Un contrato de servicios profesionales no es una relación de dependencia,
 * así que certificar «tiempo de trabajo» sería decir algo que no ocurrió: la
 * UATH emite para esos casos un certificado de prestación de servicios, con
 * otro texto. Lo confirmaron el 2026-09-25.
 */
enum TipoCertificadoLaboral: string
{
    case LABORAL              = 'laboral';
    case PRESTACION_SERVICIOS = 'prestacion_servicios';

    public static function paraRegimen(?string $regimen): self
    {
        return $regimen === RegimenLaboral::SERVICIOS_PROFESIONALES->value
            ? self::PRESTACION_SERVICIOS
            : self::LABORAL;
    }

    public function titulo(): string
    {
        return match ($this) {
            self::LABORAL              => 'CERTIFICADO LABORAL',
            self::PRESTACION_SERVICIOS => 'CERTIFICADO DE PRESTACIÓN DE SERVICIOS',
        };
    }

    /** El verbo con el que el documento describe lo que hizo la persona. */
    public function formulaDeServicio(): string
    {
        return match ($this) {
            self::LABORAL =>
                'ha prestado sus servicios institucionales en los siguientes períodos:',
            self::PRESTACION_SERVICIOS =>
                'ha prestado servicios profesionales, bajo contrato civil y sin '
                    .'relación de dependencia, en los siguientes períodos:',
        };
    }

    public function etiquetaTiempo(): string
    {
        return match ($this) {
            self::LABORAL              => 'Tiempo de servicio en la institución',
            self::PRESTACION_SERVICIOS => 'Tiempo de prestación de servicios',
        };
    }
}
