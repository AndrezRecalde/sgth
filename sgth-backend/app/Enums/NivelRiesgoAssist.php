<?php

namespace App\Enums;

/**
 * Nivel de riesgo por sustancia según la puntuación ASSIST v3.1 (manual OMS/OPS, Cap. 14).
 * Los puntos de corte son distintos para alcohol que para el resto de sustancias
 * (ver SustanciaAssist / CuestionarioAssistData::puntosCorte()).
 */
enum NivelRiesgoAssist: string
{
    case BAJO = 'bajo';
    case MODERADO = 'moderado';
    case ALTO = 'alto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BAJO => 'Riesgo bajo',
            self::MODERADO => 'Riesgo moderado',
            self::ALTO => 'Riesgo alto',
        };
    }

    /** Intervención sugerida por el manual ASSIST (Cap. 15) para cada nivel. */
    public function intervencionSugerida(): string
    {
        return match ($this) {
            self::BAJO => 'No requiere intervención',
            self::MODERADO => 'Intervención breve',
            self::ALTO => 'Derivación a servicio especializado para evaluación y tratamiento',
        };
    }
}
