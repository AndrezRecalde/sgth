<?php

namespace App\Enums;

enum MotivoEntregaEpp: string
{
    case ENTREGA = 'entrega';
    case DEVOLUCION = 'devolucion';
    case REPOSICION = 'reposicion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTREGA => 'Entrega',
            self::DEVOLUCION => 'Devolución',
            self::REPOSICION => 'Reposición',
        };
    }
}
