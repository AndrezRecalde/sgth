<?php

namespace App\Contracts\Handoff;

use App\Models\Handoff\HandoffErp;

interface HandoffErpServiceInterface
{
    /**
     * Genera un archivo de integración (Handoff) XML con la información de la nómina
     * y asegura su integridad generando un hash SHA-256.
     *
     * @param int $nominaId ID de la nómina
     * @return HandoffErp
     */
    public function generarHandoffNomina(int $nominaId): HandoffErp;

    /**
     * Genera el Handoff XML para el compromiso presupuestario del viático
     */
    public function generarHandoffCompromisoViatico(int $viaticoId): HandoffErp;

    /**
     * Genera el Handoff XML para el devengado de la liquidación del viático
     */
    public function generarHandoffDevengadoViatico(int $liquidacionId): HandoffErp;
}
