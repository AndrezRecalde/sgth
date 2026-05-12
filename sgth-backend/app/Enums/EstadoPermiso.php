<?php

namespace App\Enums;

enum EstadoPermiso: string
{
    case PENDIENTE           = 'pendiente';
    case ACTIVO              = 'activo';
    case ANULADO             = 'anulado';
    case RECHAZADO           = 'rechazado';
    case FALTA_INJUSTIFICADA = 'falta_injustificada';
    case VALIDADO_TS         = 'validado_trabajo_social';
}
