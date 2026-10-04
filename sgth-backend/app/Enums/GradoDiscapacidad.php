<?php

namespace App\Enums;

/**
 * El grado de una discapacidad según su porcentaje, con los rangos que fijó
 * Talento Humano el 2026-10-03. No se guarda: se deriva del porcentaje, así
 * los dos no pueden contradecirse.
 */
enum GradoDiscapacidad: string
{
    case LEVE      = 'leve';
    case MODERADA  = 'moderada';
    case GRAVE     = 'grave';
    case MUY_GRAVE = 'muy_grave';

    /** Por debajo de este porcentaje no se registra una discapacidad. */
    public const PORCENTAJE_MINIMO = 5;

    public static function desdePorcentaje(float|int|string|null $porcentaje): ?self
    {
        if ($porcentaje === null || $porcentaje === '') {
            return null;
        }

        $valor = (float) $porcentaje;

        return match (true) {
            $valor < self::PORCENTAJE_MINIMO => null,
            $valor < 25 => self::LEVE,
            $valor < 50 => self::MODERADA,
            $valor < 75 => self::GRAVE,
            default     => self::MUY_GRAVE,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::LEVE      => 'Leve',
            self::MODERADA  => 'Moderada',
            self::GRAVE     => 'Grave',
            self::MUY_GRAVE => 'Muy grave o completa',
        };
    }
}
