<?php

namespace App\Enums;

enum EstadoContrato: string
{
    case VIGENTE = 'vigente';
    case TERMINADO = 'terminado';
    case CANCELADO = 'cancelado';
}
