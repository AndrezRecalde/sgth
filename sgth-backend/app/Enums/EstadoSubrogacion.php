<?php

namespace App\Enums;

enum EstadoSubrogacion: string
{
    case ACTIVA     = 'activa';
    case FINALIZADA = 'finalizada';
    case CANCELADA  = 'cancelada';
}
