<?php

namespace App\Enums;

/** En qué quedó un resultado que el servidor envió desde el Portal. */
enum EstadoEnvioResultado: string
{
    /** Espera a que un médico lo mire (D2). */
    case POR_VALIDAR = 'por_validar';
    /** Un médico lo aceptó: ya es un resultado de la historia clínica. */
    case VALIDADO    = 'validado';
    /** Un médico no lo aceptó, con motivo. */
    case RECHAZADO   = 'rechazado';
    /** El servidor lo retiró antes de que lo miraran. */
    case RETIRADO    = 'retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::POR_VALIDAR => 'Por validar',
            self::VALIDADO    => 'Validado',
            self::RECHAZADO   => 'Rechazado',
            self::RETIRADO    => 'Retirado',
        };
    }
}
