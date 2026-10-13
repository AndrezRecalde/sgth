<?php
namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\HelpdeskServiceInterface;

/**
 * Las métricas de la mesa de ayuda, todavía simuladas (fases 3.5 y 4). Crear,
 * cerrar, escalar y vincular un equipo pasaron a `TicketService` y
 * `TicketEstadoService` (fase 3.1).
 */
class HelpdeskService implements HelpdeskServiceInterface
{
    public function obtenerResultadosEncuestas(array $filtros): array
    {
        // Mock de resultados estadísticos
        return [
            'promedio_general' => 4.5,
            'por_tecnico' => [],
            'por_area' => []
        ];
    }

    public function obtenerCargaTrabajoYMetricas(int $tecnicoId): array
    {
        // Mock de métricas de carga de trabajo
        return [
            'tickets_abiertos' => 5,
            'tickets_cerrados_mes' => 20,
            'promedio_resolucion_horas' => 2.5
        ];
    }
}