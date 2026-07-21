<?php

namespace App\Enums;

enum NivelRiesgoPsicosocial: string
{
    case BAJO = 'bajo';
    case MEDIO = 'medio';
    case ALTO = 'alto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BAJO => 'Riesgo bajo',
            self::MEDIO => 'Riesgo medio',
            self::ALTO => 'Riesgo alto',
        };
    }
}
