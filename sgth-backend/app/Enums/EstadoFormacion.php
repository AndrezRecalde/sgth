<?php

namespace App\Enums;

/**
 * Dónde está un título o curso del historial académico. Lo que registra
 * Talento Humano entra validado; lo que registra el servidor desde el portal,
 * por validar.
 */
enum EstadoFormacion: string
{
    case VALIDADO    = 'validado';
    case POR_VALIDAR = 'por_validar';
    case RECHAZADO   = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VALIDADO    => 'Validado',
            self::POR_VALIDAR => 'Por validar',
            self::RECHAZADO   => 'Rechazado',
        };
    }
}
