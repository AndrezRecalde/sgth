<?php

namespace App\Enums;

/**
 * El ticket de la mesa de ayuda (docs/diseno-subsistema-tic.md, 4.3). Las
 * transiciones viven en `TicketEstadoService::TRANSICIONES`.
 */
enum EstadoTicket: string
{
    case ABIERTO           = 'abierto';
    case ASIGNADO          = 'asignado';
    case EN_PROCESO        = 'en_proceso';
    case PENDIENTE_USUARIO = 'pendiente_usuario';
    case RESUELTO          = 'resuelto';
    case CERRADO           = 'cerrado';
    // Duplicado o mal registrado, con motivo.
    case ANULADO           = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ABIERTO           => 'Abierto',
            self::ASIGNADO          => 'Asignado',
            self::EN_PROCESO        => 'En proceso',
            self::PENDIENTE_USUARIO => 'Esperando al usuario',
            self::RESUELTO          => 'Resuelto',
            self::CERRADO           => 'Cerrado',
            self::ANULADO           => 'Anulado',
        };
    }

    /** Los que ya no cambian. */
    public function final(): bool
    {
        return $this === self::CERRADO || $this === self::ANULADO;
    }

    /** @return list<string> Los que siguen en la cola de alguien. */
    public static function enCurso(): array
    {
        return [self::ABIERTO->value, self::ASIGNADO->value, self::EN_PROCESO->value, self::PENDIENTE_USUARIO->value];
    }
}
