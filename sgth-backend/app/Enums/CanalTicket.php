<?php

namespace App\Enums;

/** Por dónde entró el ticket: lo abrió el servidor en el Portal o lo registró TIC (llamada o visita). */
enum CanalTicket: string
{
    case PORTAL = 'portal';
    case TIC    = 'tic';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PORTAL => 'Portal',
            self::TIC    => 'Registrado por TIC',
        };
    }
}
