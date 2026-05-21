<?php

namespace App\Enums;

enum NivelEstudio: string
{
    case PRIMARIA      = 'primaria';
    case SECUNDARIA    = 'secundaria';
    case TERCER_NIVEL  = 'tercer_nivel';
    case CUARTO_NIVEL  = 'cuarto_nivel';

    public function etiqueta(): string
    {
        return match($this) {
            self::PRIMARIA     => 'Primaria',
            self::SECUNDARIA   => 'Secundaria',
            self::TERCER_NIVEL => 'Tercer Nivel',
            self::CUARTO_NIVEL => 'Cuarto Nivel',
        };
    }
}