<?php

namespace App\Enums;

enum TipoEventoLaboral: string
{
    case NINGUNO = 'ninguno';
    case INCIDENTE = 'incidente';
    case ACCIDENTE = 'accidente';
    case ENFERMEDAD_PROFESIONAL = 'enfermedad_profesional';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NINGUNO => 'Ninguno',
            self::INCIDENTE => 'Incidente',
            self::ACCIDENTE => 'Accidente',
            self::ENFERMEDAD_PROFESIONAL => 'Enfermedad Profesional',
        };
    }
}
