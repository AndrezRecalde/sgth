<?php

namespace App\Enums;

/**
 * Por qué procedimiento se sanciona (diseño de Acciones de Personal, 9.15;
 * fase 4.4). Lo decide el régimen del servidor al abrirlo:
 *
 * - el **sumario administrativo** es el de la LOSEP (Arts. 42 a 44), con su
 *   gravedad leve o grave;
 * - el **procedimiento del reglamento interno** es el del obrero: la falta sale
 *   del catálogo de su reglamento interno de trabajo, porque el Código del
 *   Trabajo prohíbe sancionar lo que el reglamento no prevea (Art. 44 a).
 *
 * Los dos comparten los hitos —apertura, notificación, descargos, informe y
 * resolución— y el debido proceso (Constitución Art. 76).
 */
enum ProcedimientoDisciplinario: string
{
    case SUMARIO_ADMINISTRATIVO = 'sumario_administrativo';
    case REGLAMENTO_INTERNO     = 'reglamento_interno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SUMARIO_ADMINISTRATIVO => 'Sumario administrativo',
            self::REGLAMENTO_INTERNO     => 'Procedimiento del reglamento interno',
        };
    }

    public static function para(?TipoNombramiento $nombramiento): self
    {
        return $nombramiento === TipoNombramiento::CODIGO_TRABAJO
            ? self::REGLAMENTO_INTERNO
            : self::SUMARIO_ADMINISTRATIVO;
    }
}
