<?php

namespace App\Enums;

/**
 * La ausencia del titular que motiva una subrogación (LOSEP, Art. 126: el
 * titular «se encuentre legalmente ausente»; diseño, 9.5; fase 5.2).
 *
 * Las cuatro primeras están en el SGTH, y la subrogación cabe dentro de sus
 * fechas. «Otra» es la que no está —una delegación, una ausencia que se
 * resolvió fuera—, y se respalda con el número de la resolución.
 */
enum TipoAusenciaTitular: string
{
    case VACACION        = 'vacacion';
    case LICENCIA        = 'licencia';
    case REPOSO          = 'reposo';
    case ACCION_PERSONAL = 'accion_personal';
    case OTRA            = 'otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VACACION        => 'Vacaciones',
            self::LICENCIA        => 'Licencia con remuneración',
            self::REPOSO          => 'Reposo médico',
            self::ACCION_PERSONAL => 'Comisión o licencia por acción de personal',
            self::OTRA            => 'Otra ausencia',
        };
    }

    /**
     * El motivo que la subrogación guardaba elegido a mano, ahora deducido. La
     * de acción de personal es licencia salvo que sea una comisión: eso lo
     * decide quien la conoce (`AusenciaDelTitularService`).
     */
    public function motivo(): MotivoSubrogacion
    {
        return match ($this) {
            self::VACACION        => MotivoSubrogacion::VACACIONES,
            self::LICENCIA        => MotivoSubrogacion::LICENCIA,
            self::REPOSO          => MotivoSubrogacion::ENFERMEDAD,
            self::ACCION_PERSONAL => MotivoSubrogacion::LICENCIA,
            self::OTRA            => MotivoSubrogacion::OTRO,
        };
    }
}
