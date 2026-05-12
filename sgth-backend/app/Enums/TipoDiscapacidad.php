<?php

namespace App\Enums;

enum TipoDiscapacidad: string
{
    case FISICA       = 'fisica';
    case SENSORIAL    = 'sensorial';
    case INTELECTUAL  = 'intelectual';
    case PSICOSOCIAL  = 'psicosocial';
    case VISCERAL     = 'visceral';
    case MULTIPLE     = 'multiple';

    public function etiqueta(): string
    {
        return match($this) {
            self::FISICA       => 'Discapacidad Física',
            self::SENSORIAL    => 'Discapacidad Sensorial',
            self::INTELECTUAL  => 'Discapacidad Intelectual',
            self::PSICOSOCIAL  => 'Discapacidad Psicosocial o Mental',
            self::VISCERAL     => 'Discapacidad Visceral u Orgánica',
            self::MULTIPLE     => 'Discapacidad Múltiple',
        };
    }
}
