<?php

namespace App\Contracts\Helpdesk;

/**
 * Las métricas de la mesa de ayuda. Los tickets están en
 * `TicketServiceInterface` y `TicketEstadoServiceInterface` (fase 3.1); estas
 * dos siguen simuladas hasta las encuestas y la carga de trabajo (3.5 y 4).
 */
interface HelpdeskServiceInterface
{
    public function obtenerResultadosEncuestas(array $filtros): array;

    public function obtenerCargaTrabajoYMetricas(int $tecnicoId): array;
}
