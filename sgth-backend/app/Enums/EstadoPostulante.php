<?php

namespace App\Enums;

enum EstadoPostulante: string
{
    case INSCRITO            = 'inscrito';
    case EN_EVALUACION       = 'en_evaluacion';
    case APROBADO            = 'aprobado';
    case REPROBADO           = 'reprobado';
    case DESCALIFICADO       = 'descalificado';
    case SELECCIONADO        = 'seleccionado';
    case GANADOR_POTENCIAL   = 'ganador_potencial';
    case NO_SELECCIONADO     = 'no_seleccionado';
    case LISTA_ESPERA        = 'lista_espera';
    case INCORPORADO         = 'incorporado';
}
