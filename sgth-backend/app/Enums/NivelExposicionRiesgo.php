<?php

namespace App\Enums;

/**
 * Nivel de Exposición (NE) — NTP 330 (INSHT).
 */
enum NivelExposicionRiesgo: string
{
    case CONTINUADA = 'continuada';
    case FRECUENTE = 'frecuente';
    case OCASIONAL = 'ocasional';
    case ESPORADICA = 'esporadica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CONTINUADA => 'Continuada',
            self::FRECUENTE => 'Frecuente',
            self::OCASIONAL => 'Ocasional',
            self::ESPORADICA => 'Esporádica',
        };
    }

    public function valor(): int
    {
        return match ($this) {
            self::CONTINUADA => 4,
            self::FRECUENTE => 3,
            self::OCASIONAL => 2,
            self::ESPORADICA => 1,
        };
    }
}
