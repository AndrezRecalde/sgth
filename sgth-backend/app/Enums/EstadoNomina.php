<?php

namespace App\Enums;

enum EstadoNomina: string
{
    case BORRADOR      = 'borrador';
    case EN_PROCESO    = 'en_proceso';
    case CERRADA       = 'cerrada';
    case CONTABILIZADA = 'contabilizada';
    case PAGADA        = 'pagada';
}
