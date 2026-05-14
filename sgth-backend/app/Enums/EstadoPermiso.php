<?php

namespace App\Enums;

enum EstadoPermiso: string
{
    case PENDIENTE           = 'pendiente';
    case ACTIVO              = 'activo';
    case ANULADO             = 'anulado';
    case RECHAZADO           = 'rechazado';
    case FALTA_INJUSTIFICADA = 'falta_injustificada';
    case VALIDADO_TRABAJO_SOCIAL = 'validado_trabajo_social';
}
