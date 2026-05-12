<?php

namespace App\Enums;

enum TipoNombramiento: string
{
    case PERMANENTE              = 'nombramiento_permanente';
    case PROVISIONAL             = 'nombramiento_provisional';
    case SERVICIOS_OCASIONALES   = 'contrato_servicios_ocasionales';
    case LIBRE_NOMBRAMIENTO      = 'libre_nombramiento_remocion';
    case CODIGO_TRABAJO          = 'codigo_trabajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::PERMANENTE            => 'Nombramiento Permanente',
            self::PROVISIONAL           => 'Nombramiento Provisional',
            self::SERVICIOS_OCASIONALES => 'Contrato de Servicios Ocasionales',
            self::LIBRE_NOMBRAMIENTO    => 'Libre Nombramiento y Remoción',
            self::CODIGO_TRABAJO        => 'Código del Trabajo',
        };
    }

    public function esCodigoTrabajo(): bool
    {
        return $this === self::CODIGO_TRABAJO;
    }
}
