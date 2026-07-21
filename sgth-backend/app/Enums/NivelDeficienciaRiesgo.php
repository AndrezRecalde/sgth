<?php

namespace App\Enums;

/**
 * Nivel de Deficiencia (ND) — NTP 330 (INSHT).
 */
enum NivelDeficienciaRiesgo: string
{
    case MUY_DEFICIENTE = 'muy_deficiente';
    case DEFICIENTE = 'deficiente';
    case MEJORABLE = 'mejorable';
    case ACEPTABLE = 'aceptable';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MUY_DEFICIENTE => 'Muy deficiente',
            self::DEFICIENTE => 'Deficiente',
            self::MEJORABLE => 'Mejorable',
            self::ACEPTABLE => 'Aceptable',
        };
    }

    public function valor(): int
    {
        return match ($this) {
            self::MUY_DEFICIENTE => 10,
            self::DEFICIENTE => 6,
            self::MEJORABLE => 2,
            self::ACEPTABLE => 0,
        };
    }
}
