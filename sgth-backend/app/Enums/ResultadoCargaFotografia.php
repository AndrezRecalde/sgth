<?php

namespace App\Enums;

/**
 * Qué pasa con cada archivo de una carga masiva de fotos
 * (docs/diseno-comunicacion-social.md, 5.1).
 *
 * La simulación y la carga usan los mismos valores: la simulación dice qué
 * pasaría con cada archivo y la carga dice qué pasó.
 */
enum ResultadoCargaFotografia: string
{
    /** El servidor no tenía foto: se carga. */
    case NUEVA = 'nueva';

    /** Tenía foto y se pidió reemplazar: la anterior queda en el historial. */
    case REEMPLAZA = 'reemplaza';

    /** Tenía foto y no se pidió reemplazar: se omite. */
    case YA_TIENE_FOTO = 'ya_tiene_foto';

    /** El nombre del archivo no trae una cédula de 10 dígitos. */
    case SIN_CEDULA = 'sin_cedula';

    /** Ninguna ficha tiene esa cédula. */
    case SIN_SERVIDOR = 'sin_servidor';

    /** La ficha existe pero el servidor no está activo: se omite. */
    case INACTIVO = 'inactivo';

    /** Otro archivo del mismo lote ya trae esa cédula: se usa el primero. */
    case REPETIDA = 'repetida';

    /** El archivo no se pudo leer como foto, o no cumple el tamaño. */
    case ILEGIBLE = 'ilegible';

    /** Si el archivo termina con una foto cargada. */
    public function seCarga(): bool
    {
        return $this === self::NUEVA || $this === self::REEMPLAZA;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NUEVA         => 'Foto nueva',
            self::REEMPLAZA     => 'Reemplaza la foto anterior',
            self::YA_TIENE_FOTO => 'Ya tiene foto: se omite',
            self::SIN_CEDULA    => 'El nombre no trae una cédula',
            self::SIN_SERVIDOR  => 'No hay ningún servidor con esa cédula',
            self::INACTIVO      => 'El servidor no está activo: se omite',
            self::REPETIDA      => 'Cédula repetida en el lote: se omite',
            self::ILEGIBLE      => 'La imagen no se pudo usar',
        };
    }
}
