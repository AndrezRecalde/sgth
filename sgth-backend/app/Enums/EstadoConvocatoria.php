<?php

namespace App\Enums;

enum EstadoConvocatoria: string
{
    case BORRADOR              = 'borrador';
    case PUBLICADA             = 'publicada';
    case EN_EVALUACION         = 'en_evaluacion';
    case EN_EVALUACION_MEDICA  = 'en_evaluacion_medica';
    case FINALIZADA            = 'finalizada';
    case CANCELADA             = 'cancelada';
    case DESIERTA              = 'desierta';
}
