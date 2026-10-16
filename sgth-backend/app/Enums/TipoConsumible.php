<?php

namespace App\Enums;

/**
 * Qué es un consumible del catálogo de TIC (fase 5.4a) [P9]. Los de impresión
 * van a un equipo compatible; el repuesto informático (un teclado, un cable,
 * la pasta térmica) puede ir a cualquier equipo o a ninguno.
 */
enum TipoConsumible: string
{
    case TONER = 'toner';
    case TINTA = 'tinta';
    case CARTUCHO = 'cartucho';
    case CINTA = 'cinta';
    case UNIDAD_IMAGEN = 'unidad_imagen';
    case KIT_MANTENIMIENTO = 'kit_mantenimiento';
    /** Otro de impresión: una banda de transferencia, un rodillo. */
    case OTRO = 'otro';
    case REPUESTO = 'repuesto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TONER             => 'Tóner',
            self::TINTA             => 'Tinta',
            self::CARTUCHO          => 'Cartucho',
            self::CINTA             => 'Cinta',
            self::UNIDAD_IMAGEN     => 'Unidad de imagen',
            self::KIT_MANTENIMIENTO => 'Kit de mantenimiento',
            self::OTRO              => 'Otro de impresión',
            self::REPUESTO          => 'Repuesto informático',
        };
    }

    /** Se entrega a un equipo concreto, y de un modelo compatible. */
    public function deImpresion(): bool
    {
        return $this !== self::REPUESTO;
    }
}
