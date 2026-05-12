<?php

namespace App\Enums;

enum EstadoDescuentoRecurrente: string
{
    case ACTIVO     = 'activo';
    case COMPLETADO = 'completado';
    case SUSPENDIDO = 'suspendido';
}
