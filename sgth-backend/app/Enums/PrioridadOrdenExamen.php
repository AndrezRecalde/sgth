<?php

namespace App\Enums;

/** Con qué urgencia se piden los exámenes; va impresa en la orden. */
enum PrioridadOrdenExamen: string
{
    case RUTINA  = 'rutina';
    case URGENTE = 'urgente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RUTINA  => 'Rutina',
            self::URGENTE => 'Urgente',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
