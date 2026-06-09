<?php
namespace App\Enums;

enum EstadoViatico: string
{
    case SOLICITADO            = 'solicitado';
    case APROBADO              = 'aprobado';
    case CON_ANTICIPO          = 'con_anticipo';
    case EN_COMISION           = 'en_comision';
    case PENDIENTE_LIQUIDACION = 'pendiente_liquidacion';
    case LIQUIDADO             = 'liquidado';
    case CONTABILIZADO         = 'contabilizado';
    case CANCELADO             = 'cancelado';
    case RECHAZADO             = 'rechazado';
}
