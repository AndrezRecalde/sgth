<?php

namespace App\Enums;

/** Cómo nació una versión de un logo (docs/diseno-comunicacion-social.md, 4.3). */
enum OrigenRecursoIdentidad: string
{
    /** El archivo que el sistema usaba antes de este módulo, sembrado al migrar. */
    case CARGA_INICIAL = 'carga_inicial';

    case SUBIDA = 'subida';

    /** «Volver a esta versión»: una versión nueva con el archivo de una anterior. */
    case REUTILIZADA = 'reutilizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CARGA_INICIAL => 'Carga inicial',
            self::SUBIDA        => 'Subida',
            self::REUTILIZADA   => 'Reutiliza una versión anterior',
        };
    }
}
