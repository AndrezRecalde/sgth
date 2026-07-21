<?php

namespace App\Enums;

enum EstadoCumplimientoNormativa: string
{
    case CUMPLE = 'cumple';
    case NO_CUMPLE = 'no_cumple';
    case EN_PROCESO = 'en_proceso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CUMPLE => 'Cumple',
            self::NO_CUMPLE => 'No cumple',
            self::EN_PROCESO => 'En proceso',
        };
    }
}
