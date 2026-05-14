<?php

namespace App\Enums;

enum EstadoSumario: string
{
    case ABIERTO        = 'abierto';
    case EN_INSTRUCCION = 'en_instruccion';
    case EN_PRUEBA      = 'en_prueba';
    case CON_INFORME    = 'con_informe';
    case RESUELTO       = 'resuelto';
    case APELADO        = 'apelado';
    case CERRADO        = 'cerrado';
}
