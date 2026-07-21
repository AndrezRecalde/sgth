<?php

namespace App\Enums;

enum EstadoActividadPrograma: string
{
    case PENDIENTE = 'pendiente';
    case EN_PROCESO = 'en_proceso';
    case EJECUTADA = 'ejecutada';
    case NO_EJECUTADA = 'no_ejecutada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EN_PROCESO => 'En proceso',
            self::EJECUTADA => 'Ejecutada',
            self::NO_EJECUTADA => 'No ejecutada',
        };
    }
}
