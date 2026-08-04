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
     * Valor sugerido para 'puede_marcar' (marcación biométrica) al crear un
     * vínculo. Sigue la regla que dio Talento Humano: marcan los nombramientos
     * permanente, provisional y de servicios ocasionales.
     *
     * Es solo el valor por defecto del formulario, nunca una restricción: TH
     * lo edita caso por caso mientras la acción de personal está en borrador
     * —entre los obreros, por ejemplo, algunos marcan y otros no—. Servicios
     * Profesionales es el caso donde el default importa: es un contrato civil
     * sin relación de dependencia y no debería marcar nunca.
     */
    public function puedeMarcarPorDefecto(): bool
    {
        return in_array($this, [
            self::PERMANENTE,
            self::PROVISIONAL,
            self::SERVICIOS_OCASIONALES,
        ], true);
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
