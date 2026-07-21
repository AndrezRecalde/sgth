<?php

namespace App\Enums;

/**
 * Nivel de Intervención — NTP 330 (INSHT), derivado del Nivel de Riesgo (NR = NP × NC).
 */
enum NivelIntervencionRiesgo: string
{
    case I = 'i';
    case II = 'ii';
    case III = 'iii';
    case IV = 'iv';

    public function etiqueta(): string
    {
        return match ($this) {
            self::I => 'I — Situación crítica, corrección urgente',
            self::II => 'II — Corregir y adoptar medidas de control',
            self::III => 'III — Mejorar si es posible; justificar la intervención',
            self::IV => 'IV — No intervenir, salvo análisis más preciso',
        };
    }

    /**
     * Rangos oficiales NTP 330: I (4000-600), II (500-150), III (120-40), IV (20 o menos).
     */
    public static function desdeNivelRiesgo(int $nivelRiesgo): self
    {
        return match (true) {
            $nivelRiesgo >= 600 => self::I,
            $nivelRiesgo >= 150 => self::II,
            $nivelRiesgo >= 40 => self::III,
            default => self::IV,
        };
    }
}
