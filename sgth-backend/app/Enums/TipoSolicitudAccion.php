<?php

namespace App\Enums;

/**
 * Lo que el servidor puede pedir desde el portal (diseño, 9.13; fase 6.3b). Cada
 * una se atiende preparando la acción de personal de su clase.
 */
enum TipoSolicitudAccion: string
{
    case RENUNCIA                  = 'renuncia';
    case LICENCIA_SIN_REMUNERACION = 'licencia_sin_remuneracion';
    case JUBILACION                = 'jubilacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RENUNCIA                  => 'Renuncia',
            self::LICENCIA_SIN_REMUNERACION => 'Licencia sin remuneración',
            self::JUBILACION                => 'Jubilación',
        };
    }

    public function clase(): ClaseAccionPersonal
    {
        return match ($this) {
            self::RENUNCIA, self::JUBILACION => ClaseAccionPersonal::CESACION,
            self::LICENCIA_SIN_REMUNERACION  => ClaseAccionPersonal::LICENCIA_SIN_REMUNERACION,
        };
    }

    /** La causal que la solicitud ya trae; la licencia la elige el servidor. */
    public function causalFija(): ?SubtipoMovimientoPersonal
    {
        return match ($this) {
            self::RENUNCIA                  => SubtipoMovimientoPersonal::RENUNCIA,
            self::JUBILACION                => SubtipoMovimientoPersonal::JUBILACION,
            self::LICENCIA_SIN_REMUNERACION => null,
        };
    }

    /** El respaldo que se pide, en palabras, para el formulario. */
    public function respaldo(): string
    {
        return match ($this) {
            self::RENUNCIA                  => 'La carta de renuncia firmada',
            self::LICENCIA_SIN_REMUNERACION => 'El documento que respalda la causal',
            self::JUBILACION                => 'El certificado del IESS',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
