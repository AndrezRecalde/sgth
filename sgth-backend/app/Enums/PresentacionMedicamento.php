<?php

namespace App\Enums;

enum PresentacionMedicamento: string
{
    case TABLETA          = 'tableta';
    case CAPSULA          = 'capsula';
    case JARABE           = 'jarabe';
    case GOTAS            = 'gotas';
    case INYECTABLE       = 'inyectable';
    case CREMA            = 'crema';
    case SUPOSITORIO      = 'supositorio';
    case SPRAY            = 'spray';
    case PARCHE           = 'parche';
    case SOLUCION         = 'solucion';
    case POLVO            = 'polvo';
    case OTRO             = 'otro';

    public function etiqueta(): string
    {
        return match($this) {
            self::TABLETA     => 'Tableta',
            self::CAPSULA     => 'Cápsula',
            self::JARABE      => 'Jarabe / Suspensión',
            self::GOTAS       => 'Gotas',
            self::INYECTABLE  => 'Inyectable / Ampolla',
            self::CREMA       => 'Crema / Pomada',
            self::SUPOSITORIO => 'Supositorio',
            self::SPRAY       => 'Spray / Aerosol',
            self::PARCHE      => 'Parche',
            self::SOLUCION    => 'Solución oftálmica/ótica',
            self::POLVO       => 'Polvo para reconstituir',
            self::OTRO        => 'Otro',
        };
    }
}
