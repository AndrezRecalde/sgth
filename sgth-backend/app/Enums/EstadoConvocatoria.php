<?php

namespace App\Enums;

enum EstadoConvocatoria: string
{
    case BORRADOR              = 'borrador';
    case PUBLICADA             = 'publicada';
    case EN_EVALUACION         = 'en_evaluacion';
    case EN_EVALUACION_MEDICA  = 'en_evaluacion_medica';
    case FINALIZADA            = 'finalizada';
    case CANCELADA             = 'cancelada';
    case DESIERTA              = 'desierta';

    /*
    | El recorrido de un concurso formal (2026-10-05):
    |
    |   borrador ──publicar──▶ publicada ──declarar ganadores──▶ en_evaluacion_medica
    |      │                      │                                  │
    |   (se borra)        desierta / cancelada            finalizada / desierta (solo)
    |
    | En borrador se edita y se configura; publicada recibe inscripciones y
    | calificaciones; en evaluación médica se incorpora o se declara al
    | siguiente. `en_evaluacion` queda en el CHECK por datos antiguos, pero
    | ninguna acción lleva a él.
    */

    /** Ya no admite inscripciones, calificaciones ni cambios. */
    public function esTerminal(): bool
    {
        return in_array($this, [self::FINALIZADA, self::DESIERTA, self::CANCELADA], true);
    }
}
