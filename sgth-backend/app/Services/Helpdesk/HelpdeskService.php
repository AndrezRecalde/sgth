<?php
namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use App\Enums\EstadoTicket;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\Helpdesk\Ticket;
use App\Services\Helpdesk\Tickets\MetricasTickets;

/**
 * Las métricas de la mesa de ayuda. Crear, cerrar, escalar y vincular un
 * equipo pasaron a `TicketService` y `TicketEstadoService` (fase 3.1). La
 * carga del técnico es real desde la 4.1; los resultados de las encuestas
 * siguen simulados hasta el reporte de satisfacción (4.2).
 */
class HelpdeskService implements HelpdeskServiceInterface
{
    public function __construct(private readonly MetricasTickets $metricas) {}

    public function obtenerResultadosEncuestas(array $filtros): array
    {
        // Mock de resultados estadísticos
        return [
            'promedio_general' => 4.5,
            'por_tecnico' => [],
            'por_area' => []
        ];
    }

    /**
     * Lo que el técnico tiene en curso y cómo le va en el mes: devolvía 5, 20
     * y 2.5 para cualquiera.
     */
    public function obtenerCargaTrabajoYMetricas(int $tecnicoId): array
    {
        $tecnico = TecnicoDtic::findOrFail($tecnicoId);
        $suyos = fn () => Ticket::where('tecnico_id', $tecnico->user_id);
        $mes = $this->metricas->delPeriodo($suyos(), now()->startOfMonth(), now());

        return [
            'tickets_abiertos'          => $suyos()->whereIn('estado', EstadoTicket::enCurso())->count(),
            'tickets_vencidos'          => $suyos()->vencidos()->count(),
            'tickets_cerrados_mes'      => $mes['resueltos'],
            'promedio_resolucion_horas' => $mes['horas_resolucion'],
            'cumplimiento_sla'          => $mes['cumplimiento_sla'],
            'satisfaccion'              => $mes['satisfaccion'],
        ];
    }
}
