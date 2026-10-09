<?php

namespace App\Enums;

/**
 * Lo que le pasa a un vínculo laboral sin ser un acto administrativo: la
 * bitácora del expediente (diseño de Acciones de Personal, fase 1.2).
 *
 * Hasta la fase 1.2 estas filas vivían en `movimientos_personal` como si fueran
 * acciones de personal: salían en el historial de acciones, la de un contrato
 * suelto nacía como «Novedad de Contrato», y las constancias de una subrogación
 * terminada antes compartían tipo con la acción de verdad, al punto de ofrecer
 * el botón del PDF. Un acto y su bitácora no son lo mismo, y ahora no comparten
 * tabla.
 */
enum TipoEventoVinculo: string
{
    /**
     * Un contrato que nace sin acción de personal que lo respalde: la carga
     * inicial de quienes ya estaban vinculados, o un alta directa.
     */
    case CONTRATO_REGISTRADO = 'contrato_registrado';

    /** La subrogación o el encargo terminó antes de la fecha prevista. */
    case SUBROGACION_FINALIZADA = 'subrogacion_finalizada';

    /** La subrogación o el encargo se canceló después de surtir efecto. */
    case SUBROGACION_CANCELADA = 'subrogacion_cancelada';

    // Tipos de bitácora anteriores que el sistema ya no genera. Se conservan
    // solo para que el histórico migrado conserve su nombre.
    case CAMBIO_PUESTO  = 'cambio_puesto';
    case CAMBIO_REGIMEN = 'cambio_regimen';
    case EGRESO         = 'egreso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CONTRATO_REGISTRADO    => 'Contrato registrado sin acción de personal',
            self::SUBROGACION_FINALIZADA => 'Fin anticipado de subrogación o encargo',
            self::SUBROGACION_CANCELADA  => 'Cancelación de subrogación o encargo',
            self::CAMBIO_PUESTO          => 'Cambio de puesto',
            self::CAMBIO_REGIMEN         => 'Cambio de régimen',
            self::EGRESO                 => 'Egreso',
        };
    }
}
