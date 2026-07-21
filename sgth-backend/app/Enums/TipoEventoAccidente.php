<?php

namespace App\Enums;

enum TipoEventoAccidente: string
{
    case ACCIDENTE = 'accidente';
    case INCIDENTE = 'incidente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACCIDENTE => 'Accidente (con lesión)',
            self::INCIDENTE => 'Incidente (casi accidente, sin lesión)',
        };
    }
}
