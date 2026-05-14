<?php

namespace App\Enums;

enum EstadoPostulante: string
{
    case POSTULADO     = 'postulado';
    case EN_PROCESO    = 'en_proceso';
    case APROBADO      = 'aprobado';
    case REPROBADO     = 'reprobado';
    case DESCALIFICADO = 'descalificado';
}
