<?php

namespace App\Enums;

/** Con qué documento recibe TIC un consumible de bodega (fase 5.4a) [P8]. */
enum DocumentoIngresoConsumible: string
{
    case EGRESO_BODEGA = 'egreso_bodega';
    case ACTA = 'acta';
    case MEMORANDO = 'memorando';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EGRESO_BODEGA => 'Egreso de bodega',
            self::ACTA          => 'Acta',
            self::MEMORANDO     => 'Memorando',
        };
    }
}
