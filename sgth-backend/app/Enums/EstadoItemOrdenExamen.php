<?php

namespace App\Enums;

/** Cómo va cada examen de una orden. */
enum EstadoItemOrdenExamen: string
{
    case PENDIENTE    = 'pendiente';
    /** Tiene un resultado adjunto. */
    case RECIBIDO     = 'recibido';
    /** El paciente no se lo hizo o el laboratorio no lo hace, con motivo. */
    case NO_REALIZADO = 'no_realizado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
