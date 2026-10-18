<?php

namespace App\Enums;

/**
 * Lo que publica Talento Humano (docs/diseno-noticias-comunicados.md, 3.1).
 *
 * La noticia informa. El comunicado es oficial: puede llevar el número de su
 * circular o memorando y pedir confirmación de lectura.
 */
enum TipoComunicado: string
{
    case NOTICIA    = 'noticia';
    case COMUNICADO = 'comunicado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NOTICIA    => 'Noticia',
            self::COMUNICADO => 'Comunicado',
        };
    }

    /** Solo el comunicado oficial lleva referencia y puede pedir confirmación. */
    public function esOficial(): bool
    {
        return $this === self::COMUNICADO;
    }
}
