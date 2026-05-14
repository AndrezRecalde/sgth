<?php

namespace App\Enums;

enum EstadoEvaluacion: string
{
    case INICIO_PERIODO     = 'inicio_periodo';
    case EN_EVALUACION      = 'en_evaluacion';
    case RETROALIMENTACION  = 'retroalimentacion';
    case CALIFICACION_FINAL = 'calificacion_final';
    case APELACION          = 'apelacion';
    case CERRADA            = 'cerrada';
}
