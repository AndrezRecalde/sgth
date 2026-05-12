<?php

namespace App\Enums;

enum EstadoTicket: string
{
    case ABIERTO           = 'abierto';
    case ASIGNADO          = 'asignado';
    case EN_PROCESO        = 'en_proceso';
    case PENDIENTE_USUARIO = 'pendiente_usuario';
    case RESUELTO          = 'resuelto';
    case CERRADO           = 'cerrado';
}
