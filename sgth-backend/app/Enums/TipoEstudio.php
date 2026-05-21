<?php

namespace App\Enums;

enum TipoEstudio: string
{
    case ESTUDIO      = 'estudio';
    case CAPACITACION = 'capacitacion';

    public function etiqueta(): string
    {
        return match($this) {
            self::ESTUDIO      => 'Estudio',
            self::CAPACITACION => 'Capacitación',
        };
    }
}