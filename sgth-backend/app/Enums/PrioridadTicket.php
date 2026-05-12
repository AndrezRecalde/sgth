<?php

namespace App\Enums;

enum PrioridadTicket: string
{
    case BAJA    = 'baja';
    case MEDIA   = 'media';
    case ALTA    = 'alta';
    case URGENTE = 'urgente';
}
