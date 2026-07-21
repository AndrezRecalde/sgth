<?php

namespace App\Enums;

enum TipoNormativaLegal: string
{
    case LEY = 'ley';
    case REGLAMENTO = 'reglamento';
    case ACUERDO = 'acuerdo';
    case RESOLUCION = 'resolucion';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LEY => 'Ley',
            self::REGLAMENTO => 'Reglamento',
            self::ACUERDO => 'Acuerdo',
            self::RESOLUCION => 'Resolución',
            self::OTRO => 'Otro',
        };
    }
}
