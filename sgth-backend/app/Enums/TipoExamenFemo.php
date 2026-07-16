<?php

namespace App\Enums;

enum TipoExamenFemo: string
{
    case LABORATORIO = 'laboratorio';
    case IMAGEN = 'imagen';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LABORATORIO => 'Laboratorio',
            self::IMAGEN => 'Imagen',
            self::OTRO => 'Otro',
        };
    }
}
