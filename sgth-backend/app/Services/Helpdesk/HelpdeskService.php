<?php
namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\Helpdesk\Sla;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use Illuminate\Support\Facades\DB;

class HelpdeskService implements HelpdeskServiceInterface
{
    public function crearTicket(array $datos): Ticket
    {
        return DB::transaction(function () use ($datos) {
            $sla = Sla::findOrFail($datos['sla_id']);
            $datos['fecha_vencimiento_sla'] = now()->addHours($sla->tiempo_resolucion_horas);
            $datos['codigo_ticket'] = 'TIC-' . strtoupper(uniqid());
            $ticket = Ticket::create($datos);
            $categoria = CategoriaTicket::find($datos['categoria_id'] ?? null);

            if (!empty($datos['bien_informatico_id']) && $categoria && $categoria->es_hardware) {
                MantenimientoBien::create([
                    'bien_informatico_id' => $datos['bien_informatico_id'],
                    'ticket_id' => $ticket->id,
                    'tipo_mantenimiento' => 'correctivo',
                    'fecha_mantenimiento' => now()->toDateString(),
                    'descripcion' => $ticket->asunto,
                    'costo' => 0
                ]);
                $bien = BienInformatico::find($datos['bien_informatico_id']);
                if ($bien) {
                    $bien->update(['estado_operativo' => 'en_mantenimiento']);
                }
            }
            return $ticket;
        });
    }

    public function cerrarTicket(int $id, array $datos): Ticket
    {
        return DB::transaction(function () use ($id, $datos) {
            $ticket = Ticket::findOrFail($id);
            $ticket->update([
                'estado' => 'cerrado',
                'fecha_cierre' => now()
            ]);

            if ($ticket->bien_informatico_id) {
                $bien = BienInformatico::find($ticket->bien_informatico_id);
                if ($bien) {
                    $bien->update(['estado_operativo' => 'activo']);
                }
            }
            // Disparar encuesta de satisfaccion (Mock/Simulado)
            return $ticket;
        });
    }
<<<<<<< HEAD
=======

    public function escalarTicket(int $id, int $nivel): Ticket
    {
        return DB::transaction(function () use ($id, $nivel) {
            $ticket = Ticket::findOrFail($id);
            // Lógica de escalación (ej. buscar técnico de ese nivel, etc.)
            // Por simplicidad en el mock, solo asume que cambia internamente.
            return $ticket;
        });
    }

    public function vincularBienATicket(int $ticketId, int $bienId): Ticket
    {
        return DB::transaction(function () use ($ticketId, $bienId) {
            $ticket = Ticket::findOrFail($ticketId);
            $ticket->update(['bien_informatico_id' => $bienId]);

            $bien = BienInformatico::find($bienId);
            if ($bien) {
                $bien->update(['estado_operativo' => 'en_mantenimiento']);
                MantenimientoBien::create([
                    'bien_informatico_id' => $bienId,
                    'ticket_id' => $ticketId,
                    'tipo_mantenimiento' => 'correctivo',
                    'fecha_mantenimiento' => now()->toDateString(),
                    'descripcion' => $ticket->asunto,
                    'costo' => 0
                ]);
            }

            return $ticket;
        });
    }

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
>>>>>>> feature/sprint-10-inventario-helpdesk
}