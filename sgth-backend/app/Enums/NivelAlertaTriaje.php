<?php

namespace App\Enums;

/**
 * Cómo de lejos de lo esperable están los signos vitales de un triaje.
 *
 * No es una clasificación de urgencia tipo Manchester ni pretende serlo: no
 * pondera síntomas, motivo de consulta ni antecedentes. Solo dice si las
 * cifras medidas se salen de rango y cuánto, para que quien atiende lo vea
 * antes de que el paciente vuelva a la sala de espera.
 */
enum NivelAlertaTriaje: string
{
    case NORMAL   = 'normal';
    case ATENCION = 'atencion';
    case CRITICO  = 'critico';

    /** Nivel de un paciente para el que no se emite juicio (menores). */
    case NO_EVALUADO = 'no_evaluado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NORMAL      => 'Normal',
            self::ATENCION    => 'Requiere atención',
            self::CRITICO     => 'Crítico',
            self::NO_EVALUADO => 'Sin valorar',
        };
    }

    /** Para pintar en la interfaz sin que cada pantalla invente su color. */
    public function color(): string
    {
        return match ($this) {
            self::NORMAL      => 'emerald',
            self::ATENCION    => 'orange',
            self::CRITICO     => 'red',
            self::NO_EVALUADO => 'gray',
        };
    }

    /** Orden para poner delante lo que más corre. */
    public function prioridad(): int
    {
        return match ($this) {
            self::CRITICO     => 0,
            self::ATENCION    => 1,
            self::NO_EVALUADO => 2,
            self::NORMAL      => 3,
        };
    }
}
