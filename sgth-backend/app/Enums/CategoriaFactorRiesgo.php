<?php

namespace App\Enums;

enum CategoriaFactorRiesgo: string
{
    case FISICO = 'fisico';
    case QUIMICO = 'quimico';
    case BIOLOGICO = 'biologico';
    case ERGONOMICO = 'ergonomico';
    case PSICOSOCIAL = 'psicosocial';
    case MECANICO = 'mecanico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FISICO => 'Físico',
            self::QUIMICO => 'Químico',
            self::BIOLOGICO => 'Biológico',
            self::ERGONOMICO => 'Ergonómico',
            self::PSICOSOCIAL => 'Psicosocial',
            self::MECANICO => 'Mecánico',
        };
    }
}
