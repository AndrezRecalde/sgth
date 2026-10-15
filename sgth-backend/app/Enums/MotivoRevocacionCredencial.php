<?php

namespace App\Enums;

/**
 * Por qué se revoca una credencial (docs/diseno-comunicacion-social.md, 6.3).
 *
 * Revocar apaga el QR de la tarjeta que anda suelta y repone otra con un
 * código nuevo. Una tarjeta deteriorada no se revoca: se vuelve a imprimir con
 * el mismo código, porque la vieja no circula.
 */
enum MotivoRevocacionCredencial: string
{
    case PERDIDA        = 'perdida';
    case ROBO           = 'robo';
    case DATOS_ERRONEOS = 'datos_erroneos';
    case OTRO           = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PERDIDA        => 'Pérdida',
            self::ROBO           => 'Robo',
            self::DATOS_ERRONEOS => 'Datos erróneos',
            self::OTRO           => 'Otro',
        };
    }

    /** «Otro» no dice nada por sí solo: pide el detalle. */
    public function pideDetalle(): bool
    {
        return $this === self::OTRO;
    }

    /**
     * ¿La tarjeta impresa se puede recoger? Una perdida o robada no: en la
     * desvinculación no queda nada que devolver.
     */
    public function tarjetaRecuperable(): bool
    {
        return ! in_array($this, [self::PERDIDA, self::ROBO], true);
    }
}
