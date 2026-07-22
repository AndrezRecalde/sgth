<?php

namespace App\Enums;

enum TipoNombramiento: string
{
    case PERMANENTE              = 'nombramiento_permanente';
    case PROVISIONAL             = 'nombramiento_provisional';
    case SERVICIOS_OCASIONALES   = 'servicios_ocasionales';
    case LIBRE_NOMBRAMIENTO      = 'libre_nombramiento_remocion';
    case CODIGO_TRABAJO          = 'codigo_trabajo';
    case SERVICIOS_PROFESIONALES = 'servicios_profesionales';
    case ELECCION_POPULAR        = 'eleccion_popular';

    public function etiqueta(): string
    {
        return match($this) {
            self::PERMANENTE            => 'Nombramiento Permanente',
            self::PROVISIONAL           => 'Nombramiento Provisional',
            self::SERVICIOS_OCASIONALES => 'Contrato de Servicios Ocasionales',
            self::LIBRE_NOMBRAMIENTO    => 'Libre Nombramiento y Remoción',
            self::CODIGO_TRABAJO        => 'Código del Trabajo',
            self::SERVICIOS_PROFESIONALES => 'Servicios Profesionales',
            self::ELECCION_POPULAR      => 'Elección Popular',
        };
    }

    public function esCodigoTrabajo(): bool
    {
        return $this === self::CODIGO_TRABAJO;
    }

    /**
     * Régimen LOSEP vs Código de Trabajo, según la clasificación ya usada
     * en ContratoServidorService::sincronizarRegimenServidor(): Código de
     * Trabajo y Servicios Profesionales se liquidan bajo régimen de Código
     * de Trabajo; el resto (incluida Elección Popular) es LOSEP.
     */
    public function esLosep(): bool
    {
        return !in_array($this, [self::CODIGO_TRABAJO, self::SERVICIOS_PROFESIONALES], true);
    }
}
