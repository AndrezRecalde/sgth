<?php

namespace App\Enums;

enum AptitudMedica: string
{
    case APTO = 'apto';
    case APTO_CON_RESTRICCIONES = 'apto_con_restricciones';
    case EN_OBSERVACION = 'en_observacion';
    case NO_APTO = 'no_apto';

    /**
     * Si el dictamen permite incorporar al candidato o registrar la acción de
     * personal que lo exige. «En observación» no bloquea: decidido con el
     * usuario el 2026-10-02, igual que no lleva fecha de reevaluación.
     */
    public function habilitaIncorporacion(): bool
    {
        return $this !== self::NO_APTO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::APTO => 'Apto',
            self::APTO_CON_RESTRICCIONES => 'Apto con Restricciones',
            self::EN_OBSERVACION => 'Apto en Observación',
            self::NO_APTO => 'No Apto',
        };
    }
}
