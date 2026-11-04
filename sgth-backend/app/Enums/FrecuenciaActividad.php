<?php
namespace App\Enums;

/**
 * Cada cuánto se entrega el producto de una actividad. Son las opciones de la
 * columna «Frecuencia» del formato de asignación de responsabilidades
 * (IN-GEP-02-02-FOR-02), con los mismos nombres.
 */
enum FrecuenciaActividad: string
{
    case DIARIO_MES_INTEGRAL = 'diario_mes_integral';
    case DIARIO_ORDINARIO    = 'diario_ordinario';
    case SEMANAL             = 'semanal';
    case QUINCENAL           = 'quincenal';
    case MENSUAL             = 'mensual';
    case TRIMESTRAL          = 'trimestral';
    case SEMESTRAL           = 'semestral';
    case ANUAL               = 'anual';

    public function etiqueta(): string
    {
        return match($this) {
            self::DIARIO_MES_INTEGRAL => 'Diario, mes integral',
            self::DIARIO_ORDINARIO    => 'Diario ordinario',
            self::SEMANAL             => 'Semanal',
            self::QUINCENAL           => 'Quincenal',
            self::MENSUAL             => 'Mensual',
            self::TRIMESTRAL          => 'Trimestral',
            self::SEMESTRAL           => 'Semestral',
            self::ANUAL               => 'Anual',
        };
    }
}
