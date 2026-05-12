<?php

namespace App\Contracts\Viatico;

use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;

interface ViaticoServiceInterface
{
    /**
     * Crea una nueva solicitud de viático calculando su monto si pasa las validaciones de bloqueo.
     */
    public function solicitar(int $servidorId, array $datos, int $userId): Viatico;

    /**
     * Liquida un viático validando facturas y devoluciones dentro de los plazos legales.
     */
    public function liquidar(int $viaticoId, array $datos, int $userId): LiquidacionViatico;

    /**
     * Verifica si el servidor tiene bloqueadas nuevas solicitudes por tener liquidaciones pendientes y vencidas.
     */
    public function verificarBloqueo(int $servidorId): bool;
}
