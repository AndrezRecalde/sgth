<?php

namespace App\Enums;

enum EstadoTramite: string
{
    case INICIADO   = 'iniciado';
    case EN_PROCESO = 'en_proceso';
    case FINALIZADO = 'finalizado';
    case ARCHIVADO  = 'archivado';
}
