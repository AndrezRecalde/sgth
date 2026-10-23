<?php

namespace App\Enums;

/**
 * Los estados de una orden de exámenes.
 *
 * `vencida` no está aquí a propósito: es una orden `emitida` o `parcial` que
 * pasó su plazo (D5, 90 días), y se calcula al leerla en vez de escribirse.
 */
enum EstadoOrdenExamen: string
{
    /** Recién emitida: ningún resultado todavía. */
    case EMITIDA  = 'emitida';
    /** Algún examen tiene resultado y otro no. */
    case PARCIAL  = 'parcial';
    /** Todos tienen resultado o se marcaron como no realizados. */
    case COMPLETA = 'completa';
    case ANULADA  = 'anulada';

    /** Si todavía espera resultados. */
    public function abierta(): bool
    {
        return in_array($this, [self::EMITIDA, self::PARCIAL], true);
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
