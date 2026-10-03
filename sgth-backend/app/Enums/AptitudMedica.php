<?php

namespace App\Enums;

enum AptitudMedica: string
{
    // En el orden del impreso: APTO · APTO EN OBSERVACIÓN · APTO CON
    // LIMITACIONES · NO APTO. El PDF recorre cases() para la sección L.
    case APTO = 'apto';
    case EN_OBSERVACION = 'en_observacion';
    case APTO_CON_RESTRICCIONES = 'apto_con_restricciones';
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
            // Los literales del impreso. El valor guardado sigue siendo
            // `apto_con_restricciones`; lo que se lee es «limitaciones».
            self::APTO => 'Apto',
            self::EN_OBSERVACION => 'Apto en observación',
            self::APTO_CON_RESTRICCIONES => 'Apto con limitaciones',
            self::NO_APTO => 'No apto',
        };
    }
}
