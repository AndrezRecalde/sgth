<?php

namespace App\Enums;

enum EstadoPostulante: string
{
    case INSCRITO         = 'inscrito';
    case EN_EVALUACION    = 'en_evaluacion';
    case SELECCIONADO     = 'seleccionado';
    case NO_SELECCIONADO  = 'no_seleccionado';
    case LISTA_ESPERA     = 'lista_espera';
    case APROBADO         = 'aprobado';
    case REPROBADO        = 'reprobado';
    case DESCALIFICADO    = 'descalificado';
}
