<?php

namespace App\Enums;

/**
 * Estados de una Acción de Personal: borrador → suscrita → registrada →
 * notificada, más anulada como salida antes de registrarse.
 *
 * Los estados intermedios 'informe_uath' y 'dictamen_presupuestario' se
 * retiraron el 2026-07-29: no capturaban ningún dato y el flujo real de
 * Talento Humano no los usa. La verificación presupuestaria sigue existiendo
 * como guarda al suscribir (ver MovimientoPersonalStateService), no como
 * estado.
 */
enum EstadoAccionPersonal: string
{
    case BORRADOR   = 'borrador';
    case SUSCRITA   = 'suscrita';
    case REGISTRADA = 'registrada';
    case NOTIFICADA = 'notificada';
    case ANULADA    = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR   => 'Borrador',
            self::SUSCRITA   => 'Suscrita',
            self::REGISTRADA => 'Registrada',
            self::NOTIFICADA => 'Notificada',
            self::ANULADA    => 'Anulada',
        };
    }
}
