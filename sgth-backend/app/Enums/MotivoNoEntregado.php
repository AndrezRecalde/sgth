<?php

namespace App\Enums;

/**
 * Por qué el mostrador no entrega un medicamento del inventario (decisión del
 * Dispensario del 2026-10-24, opción A). «Otro» pide el detalle.
 */
enum MotivoNoEntregado: string
{
    case AGOTADO = 'agotado';
    case VENCIDO = 'vencido';
    case OTRO    = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AGOTADO => 'Agotado',
            self::VENCIDO => 'Vencido',
            self::OTRO    => 'Otro motivo',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
