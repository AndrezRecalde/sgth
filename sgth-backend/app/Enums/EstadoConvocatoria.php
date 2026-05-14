<?php

namespace App\Enums;

enum EstadoConvocatoria: string
{
    case PUBLICADA     = 'publicada';
    case EN_EVALUACION = 'en_evaluacion';
    case FINALIZADA    = 'finalizada';
    case CANCELADA     = 'cancelada';
}
