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

    /**
     * El titular cuya ausencia cubría este contrato de reemplazo cesó (fase
     * 2.1). El contrato sigue —tiene su propio plazo—, pero ya no cubre a
     * nadie: Talento Humano decide si sigue o termina.
     */
    case TITULAR_CESADO = 'titular_cesado';

    /**
     * Talento Humano fijó el fin del período de una autoridad electa que no lo
     * tenía (fase 4.1): la que ya estaba cuando el período empezó a contarse.
     */
    case FIN_DEL_PERIODO_FIJADO = 'fin_del_periodo_fijado';

    /**
     * La nueva escala de remuneraciones le subió la remuneración (fase 7.3b):
     * un acto general, sin acción de personal por servidor. `datos` guarda la
     * escala, la remuneración de antes y la nueva.
     */
    case ESCALA_APLICADA = 'escala_aplicada';

    /** La escala se revirtió por error y la remuneración volvió a la de antes. */
    case ESCALA_REVERTIDA = 'escala_revertida';

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
            self::TITULAR_CESADO         => 'El titular al que cubría cesó',
            self::FIN_DEL_PERIODO_FIJADO => 'Fin del período fijado',
            self::ESCALA_APLICADA        => 'Nueva escala de remuneraciones',
            self::ESCALA_REVERTIDA       => 'Reversión de la escala de remuneraciones',
            self::CAMBIO_PUESTO          => 'Cambio de puesto',
            self::CAMBIO_REGIMEN         => 'Cambio de régimen',
            self::EGRESO                 => 'Egreso',
        };
    }
}
