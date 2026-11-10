<?php

namespace App\Enums;

/**
 * Si el puesto es del distributivo o solo sostiene contratos que no ocupan
 * plaza (docs/diseno-manual-de-puestos.md, P9).
 *
 * - **De plantilla**: el del Manual de Puestos. Tiene plazas y partida, se
 *   concursa y cuenta en las vacantes. Lo ocupan los nombramientos, los
 *   obreros y las autoridades electas.
 * - **De contrato**: sostiene contratos de servicios ocasionales y de servicios
 *   profesionales [TH D11, D12]. No tiene plazas, no se concursa ni cuenta en
 *   las vacantes. Al de un ocasional le hacen falta grado y perfil, porque cobra
 *   según la escala y se evalúa (Reg. LOSEP 217 y 146 g).
 *
 * Qué modalidad cabe en cada uno lo decide `TipoNombramiento::admitePuesto()`.
 */
enum TipoPuesto: string
{
    case PLANTILLA = 'plantilla';
    case CONTRATO  = 'contrato';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PLANTILLA => 'De plantilla',
            self::CONTRATO  => 'De contrato',
        };
    }
}
