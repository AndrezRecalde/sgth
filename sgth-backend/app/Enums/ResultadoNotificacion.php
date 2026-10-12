<?php

namespace App\Enums;

/**
 * Qué pasó al notificar en persona (diseño, 6.4; fase 6.3a): la recibió y
 * firmó el «Recibí», o se negó a recibirla y queda la razón con un testigo.
 */
enum ResultadoNotificacion: string
{
    case RECIBIDA = 'recibida';
    case NEGADA   = 'negada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECIBIDA => 'La recibió',
            self::NEGADA   => 'Se negó a recibirla',
        };
    }
}
