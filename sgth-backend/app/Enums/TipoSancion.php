<?php

namespace App\Enums;

enum TipoSancion: string
{
    case AMONESTACION_VERBAL  = 'amonestacion_verbal';
    case AMONESTACION_ESCRITA = 'amonestacion_escrita';
    case MULTA                = 'multa';
    case SUSPENSION           = 'suspension';
    case DESTITUCION          = 'destitucion';
}
