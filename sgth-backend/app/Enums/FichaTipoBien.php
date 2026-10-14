<?php

namespace App\Enums;

/**
 * Qué características pide un tipo de equipo en su ficha (fase 5.3). Se
 * guardan en `caracteristicas_tecnicas`; el formulario muestra las de la
 * ficha del tipo elegido.
 */
enum FichaTipoBien: string
{
    /** Procesador, memoria, disco y sistema operativo. */
    case COMPUTADOR = 'computador';
    /** Conexión, consumible y si imprime a color. */
    case IMPRESORA = 'impresora';
    /** Pulgadas y entradas de video. */
    case MONITOR = 'monitor';
    /** Cómo alimenta las hojas y su conexión. */
    case ESCANER = 'escaner';
    /** Solo los datos comunes y las observaciones. */
    case GENERAL = 'general';

    public function etiqueta(): string
    {
        return match ($this) {
            self::COMPUTADOR => 'Computador',
            self::IMPRESORA  => 'Impresora',
            self::MONITOR    => 'Monitor',
            self::ESCANER    => 'Escáner',
            self::GENERAL    => 'General',
        };
    }
}
