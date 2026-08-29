<?php

namespace App\Enums;

enum EstadoPostulante: string
{
    case INSCRITO            = 'inscrito';
    case EN_EVALUACION       = 'en_evaluacion';
    case APROBADO            = 'aprobado';
    case REPROBADO           = 'reprobado';
    case DESCALIFICADO       = 'descalificado';
    case SELECCIONADO        = 'seleccionado';
    case GANADOR_POTENCIAL   = 'ganador_potencial';
    case NO_SELECCIONADO     = 'no_seleccionado';
    case LISTA_ESPERA        = 'lista_espera';
    case INCORPORADO         = 'incorporado';

    /**
     * ¿Todavía se puede calificar o recalificar al aspirante?
     *
     * Solo mientras el puntaje sea lo que decide su suerte. Una vez despachado
     * al dispensario, seleccionado o incorporado, el puntaje ya cumplió su
     * función y volver a guardarlo tendría un efecto absurdo: `guardar()`
     * recalcula el estado a aprobado/reprobado, así que recalificar a alguien
     * que está en evaluación médica lo devolvería a «aprobado» y borraría en
     * silencio que ya fue despachado.
     */
    public function admiteCalificacion(): bool
    {
        return in_array($this, [
            self::INSCRITO,
            self::EN_EVALUACION,
            self::APROBADO,
            self::REPROBADO,
        ], true);
    }
}
