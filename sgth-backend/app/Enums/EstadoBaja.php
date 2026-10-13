<?php

namespace App\Enums;

/**
 * El trámite de la baja (docs/diseno-subsistema-tic.md, 5.3): el técnico la
 * propone con su informe y el jefe de TIC la aprueba o la rechaza.
 */
enum EstadoBaja: string
{
    case PROPUESTA = 'propuesta';
    case APROBADA = 'aprobada';
    case RECHAZADA = 'rechazada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PROPUESTA => 'Propuesta',
            self::APROBADA  => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
