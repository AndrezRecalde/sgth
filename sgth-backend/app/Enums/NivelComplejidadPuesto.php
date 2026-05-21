<?php
namespace App\Enums;

enum NivelComplejidadPuesto: string
{
    case BAJO  = 'bajo';
    case MEDIO = 'medio';
    case ALTO  = 'alto';

    public function etiqueta(): string
    {
        return match($this) {
            self::BAJO  => 'Nivel Bajo',
            self::MEDIO => 'Nivel Medio',
            self::ALTO  => 'Nivel Alto',
        };
    }
}
