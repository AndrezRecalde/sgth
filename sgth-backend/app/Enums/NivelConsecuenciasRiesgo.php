<?php

namespace App\Enums;

/**
 * Nivel de Consecuencias (NC) — NTP 330 (INSHT).
 */
enum NivelConsecuenciasRiesgo: string
{
    case MORTAL_CATASTROFICO = 'mortal_catastrofico';
    case MUY_GRAVE = 'muy_grave';
    case GRAVE = 'grave';
    case LEVE = 'leve';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MORTAL_CATASTROFICO => 'Mortal o catastrófico',
            self::MUY_GRAVE => 'Muy grave',
            self::GRAVE => 'Grave',
            self::LEVE => 'Leve',
        };
    }

    public function valor(): int
    {
        return match ($this) {
            self::MORTAL_CATASTROFICO => 100,
            self::MUY_GRAVE => 60,
            self::GRAVE => 25,
            self::LEVE => 10,
        };
    }
}
