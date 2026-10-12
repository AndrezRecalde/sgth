<?php

namespace App\Enums;

/**
 * En qué quedó una solicitud del portal (fase 6.3b): pendiente hasta que
 * Talento Humano prepara su acción —atendida— o la rechaza con el motivo; el
 * servidor la puede retirar mientras está pendiente.
 */
enum EstadoSolicitudAccion: string
{
    case PENDIENTE = 'pendiente';
    case ATENDIDA  = 'atendida';
    case RECHAZADA = 'rechazada';
    case RETIRADA  = 'retirada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ATENDIDA  => 'Atendida',
            self::RECHAZADA => 'Rechazada',
            self::RETIRADA  => 'Retirada',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
