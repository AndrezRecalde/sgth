<?php

namespace App\Enums;

enum EstadoViatico: string
{
    case SOLICITADO            = 'solicitado';
    case APROBADO_JEFE         = 'aprobado_jefe';
    case APROBADO_DIRECTOR     = 'aprobado_director';
    case APROBADO_AUTORIDAD    = 'aprobado_autoridad';
    case APROBADO_UATH         = 'aprobado_uath';
    case APROBADO_FINANCIERO   = 'aprobado_financiero';
    case CON_ANTICIPO          = 'con_anticipo';
    case EN_COMISION           = 'en_comision';
    case PENDIENTE_LIQUIDACION = 'pendiente_liquidacion';
    case LIQUIDADO             = 'liquidado';
    case CONTABILIZADO         = 'contabilizado';
}
