<?php

namespace App\Enums;

/**
 * Lo que una novedad le dice a Financiero sobre el rol de pagos del mes (fase
 * 7.3a): qué pasó, no cuánto. Desde el 2026-10-20 el SGTH es informativo y los
 * valores los calcula Financiero.
 */
enum ConceptoNovedadNomina: string
{
    /** Entra a mitad del mes, o al empezar: los días desde que entra, y su remuneración. */
    case INGRESO = 'ingreso';
    /** Sale en el mes: los días hasta que sale. */
    case SALIDA = 'salida';
    /** La remuneración cambia en el mes: desde cuándo, la de antes y la nueva. */
    case CAMBIO_REMUNERACION = 'cambio_remuneracion';
    /** Licencia o comisión sin remuneración, o suspensión: los días que no se pagan. */
    case DIAS_SIN_REMUNERACION = 'dias_sin_remuneracion';
    /** LOSEP Art. 126: los días, con la remuneración del puesto que subroga y la suya. */
    case SUBROGACION = 'subrogacion';
    /** LOSEP Art. 127: los días, con la remuneración del puesto encargado y la suya. */
    case ENCARGO = 'encargo';
    /** La sanción pecuniaria: el porcentaje de la remuneración. */
    case MULTA = 'multa';
    /** Lo que se le debe por el tiempo fuera: la restitución y el reintegro del obrero. */
    case REMUNERACIONES_PENDIENTES = 'remuneraciones_pendientes';
    /** Los días de vacaciones no gozadas, al salir (fase 7.4b). */
    case VACACIONES_NO_GOZADAS = 'vacaciones_no_gozadas';
    /** La indemnización o compensación por la salida, con su resolución. */
    case INDEMNIZACION = 'indemnizacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO                   => 'Ingreso',
            self::SALIDA                    => 'Salida',
            self::CAMBIO_REMUNERACION       => 'Cambio de remuneración',
            self::DIAS_SIN_REMUNERACION     => 'Días sin remuneración',
            self::SUBROGACION               => 'Subrogación',
            self::ENCARGO                   => 'Encargo',
            self::MULTA                     => 'Multa',
            self::REMUNERACIONES_PENDIENTES => 'Remuneraciones pendientes',
            self::VACACIONES_NO_GOZADAS     => 'Vacaciones no gozadas',
            self::INDEMNIZACION             => 'Indemnización',
        };
    }
}
