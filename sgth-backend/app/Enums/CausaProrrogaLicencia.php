<?php

namespace App\Enums;

/**
 * Por qué el Consejo Provincial prorroga una licencia más allá de los 60 días
 * acumulados (COOTAD Art. 47 s; fase 4.1b).
 */
enum CausaProrrogaLicencia: string
{
    case ENFERMEDAD_CATASTROFICA = 'enfermedad_catastrofica';
    case CALAMIDAD_DOMESTICA     = 'calamidad_domestica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENFERMEDAD_CATASTROFICA => 'Enfermedad catastrófica',
            self::CALAMIDAD_DOMESTICA     => 'Calamidad doméstica',
        };
    }
}
