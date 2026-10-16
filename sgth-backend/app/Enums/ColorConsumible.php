<?php

namespace App\Enums;

/** El color de un consumible de impresión (fase 5.4a); sin color, no aplica. */
enum ColorConsumible: string
{
    case NEGRO = 'negro';
    case CIAN = 'cian';
    case MAGENTA = 'magenta';
    case AMARILLO = 'amarillo';
    case TRICOLOR = 'tricolor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NEGRO    => 'Negro',
            self::CIAN     => 'Cian',
            self::MAGENTA  => 'Magenta',
            self::AMARILLO => 'Amarillo',
            self::TRICOLOR => 'Tricolor',
        };
    }
}
