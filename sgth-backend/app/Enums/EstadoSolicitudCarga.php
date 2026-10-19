<?php

namespace App\Enums;

/**
 * Dónde está una solicitud de carga familiar del Portal (diseño, 3.3). Los
 * nombres siguen los de las solicitudes de acciones de personal, salvo
 * «validada», que es la palabra de los títulos.
 */
enum EstadoSolicitudCarga: string
{
    case PENDIENTE = 'pendiente';
    case VALIDADA  = 'validada';
    case RECHAZADA = 'rechazada';
    case RETIRADA  = 'retirada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::VALIDADA  => 'Validada',
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
