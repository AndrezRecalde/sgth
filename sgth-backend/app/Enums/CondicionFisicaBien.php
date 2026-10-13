<?php

namespace App\Enums;

/**
 * Cómo está físicamente un equipo informático, independiente de dónde esté
 * (ver `EstadoBien`). Son los tres valores de la columna «ESTADO EQUIPO» del
 * Excel de TIC y de la pestaña de impresoras.
 */
enum CondicionFisicaBien: string
{
    case BUENO = 'bueno';
    case REGULAR = 'regular';
    case MALO = 'malo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BUENO   => 'Bueno',
            self::REGULAR => 'Regular',
            self::MALO    => 'Malo',
        };
    }
}
