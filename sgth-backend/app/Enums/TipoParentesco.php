<?php

namespace App\Enums;

enum TipoParentesco: string
{
    case CONYUGUE = 'conyugue';
    case HIJO     = 'hijo';

    public function etiqueta(): string
    {
        return match($this) {
            self::CONYUGUE => 'Cónyuge',
            self::HIJO     => 'Hijo/a',
        };
    }
}