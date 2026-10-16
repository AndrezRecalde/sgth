<?php

namespace App\Enums;

/** Cómo viene un consumible (fase 5.4a): la cantidad se cuenta en esta presentación. */
enum PresentacionConsumible: string
{
    case UNIDAD = 'unidad';
    case BOTELLA = 'botella';
    case CAJA = 'caja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UNIDAD  => 'Unidad',
            self::BOTELLA => 'Botella',
            self::CAJA    => 'Caja',
        };
    }
}
