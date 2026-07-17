<?php

namespace App\Enums;

enum DenticionTipo: string
{
    case PERMANENTE = 'permanente';
    case TEMPORAL = 'temporal';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PERMANENTE => 'Permanente',
            self::TEMPORAL => 'Temporal',
        };
    }
}
