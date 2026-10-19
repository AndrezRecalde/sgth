<?php

namespace App\Enums;

/**
 * Lo que el servidor pide desde «Mi perfil» sobre sus cargas familiares
 * (docs/diseno-cargas-familiares-portal.md, 3.2). Ninguna cuenta hasta que
 * Talento Humano la valida.
 */
enum TipoSolicitudCarga: string
{
    case ALTA       = 'alta';
    case CORRECCION = 'correccion';
    case BAJA       = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ALTA       => 'Registro',
            self::CORRECCION => 'Corrección',
            self::BAJA       => 'Baja',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
