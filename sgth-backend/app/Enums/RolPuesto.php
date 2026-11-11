<?php
namespace App\Enums;

/**
 * El rol del puesto, del más alto al más bajo, como el Manual de Puestos.
 *
 * `direccion_unidad` y `ejecucion_supervision` entraron el 2026-11-10 con la
 * respuesta de Talento Humano a D3 (docs/diseno-manual-de-puestos.md, P2): el
 * manual los usa en 16 y 34 fichas. El de apoyo se llama como en el manual.
 * `dignatario` queda para las autoridades electas aunque el manual las ponga en
 * «Dirección de unidad»: con él, el sistema sabe que su puesto no se traspasa
 * ni se reclasifica con una acción de personal.
 */
enum RolPuesto: string
{
    case DIGNATARIO                    = 'dignatario';
    case DIRECCION_UNIDAD              = 'direccion_unidad';
    case EJECUCION_COORDINACION        = 'ejecucion_coordinacion';
    case EJECUCION_SUPERVISION         = 'ejecucion_supervision';
    case EJECUCION_PROCESOS            = 'ejecucion_procesos';
    case EJECUCION_PROCESOS_APOYO      = 'ejecucion_procesos_apoyo';
    case ADMINISTRATIVO                = 'administrativo';
    case CODIGO_TRABAJO                = 'codigo_trabajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::DIGNATARIO               => 'Dignatarios',
            self::DIRECCION_UNIDAD         => 'Dirección de Unidad Organizacional',
            self::EJECUCION_COORDINACION   => 'Ejecución y Coordinación de Procesos',
            self::EJECUCION_SUPERVISION    => 'Ejecución y Supervisión de Procesos',
            self::EJECUCION_PROCESOS       => 'Ejecución de Procesos',
            self::EJECUCION_PROCESOS_APOYO => 'Ejecución de Procesos de Apoyo y Tecnológico',
            self::ADMINISTRATIVO           => 'Administrativo',
            self::CODIGO_TRABAJO           => 'Código del Trabajo',
        };
    }
}
