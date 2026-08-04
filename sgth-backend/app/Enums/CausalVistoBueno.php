<?php

namespace App\Enums;

/**
 * Causales por las que el empleador puede solicitar visto bueno para dar por
 * terminado el contrato de un obrero — Art. 172 del Código del Trabajo, los
 * siete numerales.
 *
 * No se modelan las del Art. 173 (visto bueno solicitado por el trabajador):
 * confirmado con Talento Humano que el GAD no las registra en el sistema.
 */
enum CausalVistoBueno: string
{
    case FALTAS_PUNTUALIDAD_ASISTENCIA = 'faltas_puntualidad_asistencia';
    case INDISCIPLINA_DESOBEDIENCIA    = 'indisciplina_desobediencia';
    case FALTA_PROBIDAD                = 'falta_probidad';
    case INJURIAS_GRAVES               = 'injurias_graves';
    case INEPTITUD_MANIFIESTA          = 'ineptitud_manifiesta';
    case DENUNCIA_INJUSTIFICADA_IESS   = 'denuncia_injustificada_iess';
    case INCUMPLIMIENTO_SEGURIDAD      = 'incumplimiento_seguridad';

    public function numeral(): int
    {
        return match ($this) {
            self::FALTAS_PUNTUALIDAD_ASISTENCIA => 1,
            self::INDISCIPLINA_DESOBEDIENCIA    => 2,
            self::FALTA_PROBIDAD                => 3,
            self::INJURIAS_GRAVES               => 4,
            self::INEPTITUD_MANIFIESTA          => 5,
            self::DENUNCIA_INJUSTIFICADA_IESS   => 6,
            self::INCUMPLIMIENTO_SEGURIDAD      => 7,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::FALTAS_PUNTUALIDAD_ASISTENCIA => 'Faltas repetidas de puntualidad o asistencia, o abandono del trabajo',
            self::INDISCIPLINA_DESOBEDIENCIA    => 'Indisciplina o desobediencia graves a los reglamentos internos',
            self::FALTA_PROBIDAD                => 'Falta de probidad o conducta inmoral',
            self::INJURIAS_GRAVES               => 'Injurias graves al empleador o su representante',
            self::INEPTITUD_MANIFIESTA          => 'Ineptitud manifiesta para la labor contratada',
            self::DENUNCIA_INJUSTIFICADA_IESS   => 'Denuncia injustificada contra el empleador ante el Seguro Social',
            self::INCUMPLIMIENTO_SEGURIDAD      => 'No acatar las medidas de seguridad, prevención e higiene',
        };
    }

    public function referenciaLegal(): string
    {
        return "Art. 172 núm. {$this->numeral()} del Código del Trabajo";
    }
}
