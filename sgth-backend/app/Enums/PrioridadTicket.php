<?php

namespace App\Enums;

/** La prioridad del ticket. La pone TIC al asignar; con la categoría decide el SLA (fase 3.2). */
enum PrioridadTicket: string
{
    case BAJA    = 'baja';
    case MEDIA   = 'media';
    case ALTA    = 'alta';
    case URGENTE = 'urgente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BAJA    => 'Baja',
            self::MEDIA   => 'Media',
            self::ALTA    => 'Alta',
            self::URGENTE => 'Urgente',
        };
    }
}
