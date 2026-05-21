<?php

namespace App\Enums;

enum NacionalidadEstudio: string
{
    case NACIONAL       = 'nacional';
    case INTERNACIONAL  = 'internacional';

    public function etiqueta(): string
    {
        return match($this) {
            self::NACIONAL      => 'Nacional',
            self::INTERNACIONAL => 'Internacional',
        };
    }
}