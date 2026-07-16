<?php

namespace App\Enums;

enum CategoriaRiesgoLaboral: string
{
    case FISICO = 'fisico';
    case SEGURIDAD = 'seguridad';
    case QUIMICO = 'quimico';
    case BIOLOGICO = 'biologico';
    case ERGONOMICO = 'ergonomico';
    case PSICOSOCIAL = 'psicosocial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FISICO => 'Físico',
            self::SEGURIDAD => 'De Seguridad',
            self::QUIMICO => 'Químico',
            self::BIOLOGICO => 'Biológico',
            self::ERGONOMICO => 'Ergonómico',
            self::PSICOSOCIAL => 'Psicosocial',
        };
    }
}
