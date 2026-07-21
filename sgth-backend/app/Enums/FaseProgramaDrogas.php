<?php

namespace App\Enums;

/**
 * Las 6 fases del Programa de prevención integral del uso y consumo de alcohol, tabaco
 * u otras drogas en espacios laborales, según el Instructivo MDT-MSP (Acuerdo Interministerial
 * Nro. MDT-MSP-2019-038, Registro Oficial Nro. 114 del 06/01/2020), sección 5.
 */
enum FaseProgramaDrogas: string
{
    case FASE_1_PREPARACION = 'fase_1_preparacion';
    case FASE_2_EQUIPO_MULTIDISCIPLINARIO = 'fase_2_equipo_multidisciplinario';
    case FASE_3_SOCIALIZACION = 'fase_3_socializacion';
    case FASE_4_DIAGNOSTICO = 'fase_4_diagnostico';
    case FASE_5_ACTUACION = 'fase_5_actuacion';
    case FASE_6_SEGUIMIENTO = 'fase_6_seguimiento';

    public function orden(): int
    {
        return match ($this) {
            self::FASE_1_PREPARACION => 1,
            self::FASE_2_EQUIPO_MULTIDISCIPLINARIO => 2,
            self::FASE_3_SOCIALIZACION => 3,
            self::FASE_4_DIAGNOSTICO => 4,
            self::FASE_5_ACTUACION => 5,
            self::FASE_6_SEGUIMIENTO => 6,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::FASE_1_PREPARACION => 'Fase 1: Preparación — compromiso de los directivos',
            self::FASE_2_EQUIPO_MULTIDISCIPLINARIO => 'Fase 2: Conformación del equipo multidisciplinario',
            self::FASE_3_SOCIALIZACION => 'Fase 3: Socialización de la implementación del programa',
            self::FASE_4_DIAGNOSTICO => 'Fase 4: Diagnóstico (tamizaje)',
            self::FASE_5_ACTUACION => 'Fase 5: Actuación — implementación',
            self::FASE_6_SEGUIMIENTO => 'Fase 6: Seguimiento y evaluación del programa',
        };
    }
}
