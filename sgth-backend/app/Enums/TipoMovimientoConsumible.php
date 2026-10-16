<?php

namespace App\Enums;

/**
 * Lo que cambia el saldo de un consumible (fase 5.4a). La lista de sus
 * movimientos es su kárdex.
 */
enum TipoMovimientoConsumible: string
{
    /** El saldo contado el día de arranque; uno por consumible [P7]. */
    case APERTURA = 'apertura';
    /** Lo que TIC recibe de bodega, con su documento [P8]. */
    case INGRESO = 'ingreso';
    /** Lo que sale a un equipo o a un servidor. */
    case ENTREGA = 'entrega';
    /** Daño, caducidad o diferencia en el conteo: suma o resta. */
    case AJUSTE = 'ajuste';

    public function etiqueta(): string
    {
        return match ($this) {
            self::APERTURA => 'Apertura',
            self::INGRESO  => 'Ingreso',
            self::ENTREGA  => 'Entrega',
            self::AJUSTE   => 'Ajuste',
        };
    }
}
