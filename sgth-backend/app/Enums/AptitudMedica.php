<?php

namespace App\Enums;

enum AptitudMedica: string
{
    case APTO = 'apto';
    case APTO_CON_RESTRICCIONES = 'apto_con_restricciones';
    case EN_OBSERVACION = 'en_observacion';
    case NO_APTO = 'no_apto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::APTO => 'Apto',
            self::APTO_CON_RESTRICCIONES => 'Apto con Restricciones',
            self::EN_OBSERVACION => 'Apto en Observación',
            self::NO_APTO => 'No Apto',
        };
    }
}
