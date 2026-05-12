<?php

namespace App\Enums;

enum EstadoCita: string
{
    case PENDIENTE  = 'pendiente';
    case CONFIRMADA = 'confirmada';
    case ATENDIDA   = 'atendida';
    case CANCELADA  = 'cancelada';
}
