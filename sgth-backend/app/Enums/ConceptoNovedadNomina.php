<?php

namespace App\Enums;

/**
 * Lo que una novedad le dice a Financiero sobre el rol de pagos del mes (fase
 * 7.3a). El monto es siempre una referencia: positivo, lo que se paga;
 * negativo, lo que se descuenta.
 */
enum ConceptoNovedadNomina: string
{
    /** Entra a mitad del mes, o al empezar: se le pagan los días desde que entra. */
    case INGRESO = 'ingreso';
    /** Sale en el mes: se le pagan los días hasta que sale. */
    case SALIDA = 'salida';
    /** La remuneración cambia en el mes: la diferencia de los días que restan. */
    case CAMBIO_REMUNERACION = 'cambio_remuneracion';
    /** Licencia o comisión sin remuneración, o suspensión: los días no se pagan. */
    case DIAS_SIN_REMUNERACION = 'dias_sin_remuneracion';
    /** LOSEP Art. 126: la diferencia con la remuneración del puesto que subroga. */
    case SUBROGACION = 'subrogacion';
    /** LOSEP Art. 127: la diferencia con la remuneración del puesto encargado. */
    case ENCARGO = 'encargo';
    /** La sanción pecuniaria, un porcentaje de la remuneración. */
    case MULTA = 'multa';
    /** Lo que se le debe por el tiempo fuera: la restitución y el reintegro del obrero. */
    case REMUNERACIONES_PENDIENTES = 'remuneraciones_pendientes';

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
        };
    }
}
