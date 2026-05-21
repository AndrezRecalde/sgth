<?php
namespace App\Enums;

enum RolPuesto: string
{
    case DIGNATARIO                    = 'dignatario';
    case EJECUCION_COORDINACION        = 'ejecucion_coordinacion';
    case EJECUCION_PROCESOS            = 'ejecucion_procesos';
    case EJECUCION_PROCESOS_APOYO      = 'ejecucion_procesos_apoyo';
    case ADMINISTRATIVO                = 'administrativo';
    case CODIGO_TRABAJO                = 'codigo_trabajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::DIGNATARIO               => 'Dignatarios',
            self::EJECUCION_COORDINACION   => 'Ejecución y Coordinación de Procesos',
            self::EJECUCION_PROCESOS       => 'Ejecución de Procesos',
            self::EJECUCION_PROCESOS_APOYO => 'Ejecución de Procesos de Apoyo',
            self::ADMINISTRATIVO           => 'Administrativo',
            self::CODIGO_TRABAJO           => 'Código del Trabajo',
        };
    }
}
