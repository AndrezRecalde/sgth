<?php

namespace App\Enums;

/**
 * Cómo llegó una fotografía del servidor (docs/diseno-comunicacion-social.md,
 * 5.1): de una en una, con el recorte hecho a mano, o en la carga masiva, con
 * el recorte centrado automático.
 */
enum OrigenFotografia: string
{
    case INDIVIDUAL = 'individual';
    case CARGA_MASIVA = 'carga_masiva';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INDIVIDUAL   => 'Subida individual',
            self::CARGA_MASIVA => 'Carga masiva',
        };
    }
}
