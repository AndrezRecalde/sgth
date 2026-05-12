<?php

namespace App\Enums;

enum TipoSubrogacion: string
{
    case SUBROGACION = 'subrogacion';
    case ENCARGO     = 'encargo';

    public function etiqueta(): string
    {
        return match($this) {
            self::SUBROGACION => 'Subrogación',
            self::ENCARGO     => 'Encargo',
        };
    }
}
