<?php

namespace App\Enums;

/**
 * El préstamo de equipos (docs/diseno-subsistema-tic.md, 4.6):
 *
 *   solicitado ─aprobar─► reservado ─entregar─► en_curso ─recibir─► devuelto
 *       ├─rechazar─► rechazado  ├─cancelar─► cancelado
 *       └─cancelar─► cancelado  └─no retirado a tiempo─► no_retirado
 *
 * «Vencido» no es un estado: es un préstamo en curso cuya hora prevista de
 * devolución ya pasó.
 */
enum EstadoPrestamo: string
{
    case SOLICITADO = 'solicitado';
    case RESERVADO = 'reservado';
    case EN_CURSO = 'en_curso';
    case DEVUELTO = 'devuelto';
    case RECHAZADO = 'rechazado';
    case CANCELADO = 'cancelado';
    case NO_RETIRADO = 'no_retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SOLICITADO  => 'Solicitado',
            self::RESERVADO   => 'Reservado',
            self::EN_CURSO    => 'En curso',
            self::DEVUELTO    => 'Devuelto',
            self::RECHAZADO   => 'Rechazado',
            self::CANCELADO   => 'Cancelado',
            self::NO_RETIRADO => 'No retirado',
        };
    }

    /** Los que ocupan el horario del equipo: la restricción de exclusión solo los mira a ellos. */
    public function bloquea(): bool
    {
        return $this === self::RESERVADO || $this === self::EN_CURSO;
    }

    /** Los que ya no cambian. */
    public function cerrado(): bool
    {
        return in_array($this, [self::DEVUELTO, self::RECHAZADO, self::CANCELADO, self::NO_RETIRADO], true);
    }

    /** @return list<string> */
    public static function queBloquean(): array
    {
        return [self::RESERVADO->value, self::EN_CURSO->value];
    }
}
