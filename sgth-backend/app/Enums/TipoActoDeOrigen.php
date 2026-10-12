<?php

namespace App\Enums;

/**
 * El acto que origina la acción de personal, como lo pide el encabezado del
 * formulario del Ministerio: decreto, acuerdo o resolución, con su número y su
 * fecha (diseño, 6.5; fase 6.1). Sin él, las tres casillas salen vacías.
 */
enum TipoActoDeOrigen: string
{
    case DECRETO    = 'decreto';
    case ACUERDO    = 'acuerdo';
    case RESOLUCION = 'resolucion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DECRETO    => 'Decreto',
            self::ACUERDO    => 'Acuerdo',
            self::RESOLUCION => 'Resolución',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
