<?php

namespace App\Enums;

enum TipoMovimientoPersonal: string
{
    case TRASLADO                  = 'traslado';
    case ASCENSO                   = 'ascenso';
    case SUBROGACION               = 'subrogacion';
    case COMISION_SERVICIOS        = 'comision_servicios';
    case CAMBIO_REGIMEN            = 'cambio_regimen';
    case CAMBIO_PUESTO             = 'cambio_puesto';
    case INGRESO                   = 'ingreso';
    case EGRESO                    = 'egreso';
    case NOVEDAD_CONTRATO          = 'novedad_contrato';
    // Acciones de personal formales (Sprint E-04)
    case CAMBIO_DENOMINACION       = 'cambio_denominacion';
    case PRESTACION_SERVICIOS      = 'prestacion_servicios';
    case CAMBIO_ADMINISTRATIVO     = 'cambio_administrativo';
    case COMISION_SIN_REMUNERACION = 'comision_sin_remuneracion';
    case LICENCIA_SIN_REMUNERACION = 'licencia_sin_remuneracion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRASLADO                  => 'Traslado',
            self::ASCENSO                   => 'Ascenso',
            self::SUBROGACION               => 'Subrogación',
            self::COMISION_SERVICIOS        => 'Comisión de Servicios',
            self::CAMBIO_REGIMEN            => 'Cambio de Régimen',
            self::CAMBIO_PUESTO             => 'Cambio de Puesto',
            self::INGRESO                   => 'Ingreso',
            self::EGRESO                    => 'Egreso',
            self::NOVEDAD_CONTRATO          => 'Novedad de Contrato',
            self::CAMBIO_DENOMINACION       => 'Cambio de Denominación',
            self::PRESTACION_SERVICIOS      => 'Prestación de Servicios',
            self::CAMBIO_ADMINISTRATIVO     => 'Cambio Administrativo',
            self::COMISION_SIN_REMUNERACION => 'Comisión de Servicios sin Remuneración',
            self::LICENCIA_SIN_REMUNERACION => 'Licencia sin Remuneración',
        };
    }

    /**
     * Solo las 5 "acciones de personal" formales tienen restricción de
     * elegibilidad por tipo de nombramiento; los movimientos históricos
     * genéricos (traslado, ascenso, etc.) no la tienen.
     */
    public function esAccionDePersonal(): bool
    {
        return in_array($this, [
            self::CAMBIO_DENOMINACION,
            self::PRESTACION_SERVICIOS,
            self::CAMBIO_ADMINISTRATIVO,
            self::COMISION_SIN_REMUNERACION,
            self::LICENCIA_SIN_REMUNERACION,
        ], true);
    }

    /**
     * Reglas de elegibilidad de Talento Humano por tipo de nombramiento
     * vigente del servidor:
     * - Cambio de denominación: solo obreros (Código de Trabajo).
     * - Prestación de servicios: solo LOSEP, excepto Permanente.
     * - Cambio administrativo: solo Nombramiento Permanente.
     * - Comisión de servicios sin remuneración: solo Permanente
     *   (+ validación de antigüedad y duración en el servicio).
     * - Licencia sin remuneración: Permanente, Código de Trabajo o
     *   Elección Popular.
     */
    public function elegiblePara(TipoNombramiento $tipo): bool
    {
        return match ($this) {
            self::CAMBIO_DENOMINACION =>
                $tipo === TipoNombramiento::CODIGO_TRABAJO,
            self::PRESTACION_SERVICIOS =>
                $tipo->esLosep() && $tipo !== TipoNombramiento::PERMANENTE,
            self::CAMBIO_ADMINISTRATIVO,
            self::COMISION_SIN_REMUNERACION =>
                $tipo === TipoNombramiento::PERMANENTE,
            self::LICENCIA_SIN_REMUNERACION => in_array($tipo, [
                TipoNombramiento::PERMANENTE,
                TipoNombramiento::CODIGO_TRABAJO,
                TipoNombramiento::ELECCION_POPULAR,
            ], true),
            default => true,
        };
    }
}
