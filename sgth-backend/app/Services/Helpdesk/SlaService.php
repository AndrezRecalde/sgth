<?php
namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\SlaServiceInterface;
use App\Models\Helpdesk\Ticket;
use App\Models\Helpdesk\TecnicoDtic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

final class SlaService implements SlaServiceInterface
{
    public function verificarTicketsAbiertos(): void
    {
        // Traer tickets que no estén resueltos o cerrados
        $ticketsAbiertos = Ticket::with('sla', 'tecnico')
            ->whereNotIn('estado', ['resuelto', 'cerrado'])
            ->get();

        $ahora = now();

        foreach ($ticketsAbiertos as $ticket) {
            $vencimiento = $ticket->fecha_vencimiento_sla;

            if (!$vencimiento) {
                continue;
            }

            // 1. Escalar automáticamente si el SLA venció
            if ($ahora->greaterThanOrEqualTo($vencimiento)) {
                $this->escalarTicket($ticket->id);
                continue;
            }

            // 2. Alertar cuando queda el 25% del tiempo (ha transcurrido el 75%)
            $fechaCreacion = $ticket->created_at;
            $horasTotalesSla = $ticket->sla->tiempo_resolucion_horas;
            
            // Calculamos el tiempo transcurrido en horas
            $horasTranscurridas = $fechaCreacion->diffInHours($ahora);
            $porcentajeTranscurrido = ($horasTranscurridas / $horasTotalesSla) * 100;

            if ($porcentajeTranscurrido >= 75) {
                // Aquí el sistema dispararía una Notification/Email real al técnico
                $tecnicoNombre = $ticket->tecnico ? $ticket->tecnico->name : 'Sin asignar';
                Log::warning("ALERTA SLA: El ticket {$ticket->codigo_ticket} está al {$porcentajeTranscurrido}% de vencer. Técnico: {$tecnicoNombre}");
            }
        }
    }

    public function escalarTicket(int $ticketId): void
    {
        DB::transaction(function () use ($ticketId) {
            $ticket = Ticket::with('tecnico')->findOrFail($ticketId);
            
            // Determinar nivel actual
            $nivelActual = 1; // Por defecto
            if ($ticket->tecnico_id) {
                $relacionTecnico = TecnicoDtic::where('user_id', $ticket->tecnico_id)->first();
                if ($relacionTecnico) {
                    $nivelActual = $relacionTecnico->nivel;
                }
            }

            $nuevoNivel = $nivelActual < 3 ? $nivelActual + 1 : 3;

            if ($nivelActual === $nuevoNivel) {
                // Ya está en el máximo nivel, solo registrar la infracción de SLA
                Log::error("SLA VENCIDO: El ticket {$ticket->codigo_ticket} ha vencido y ya está en el Nivel Máximo 3.");
                return;
            }

            // Escalar al siguiente nivel
            // Lógica para asignar aleatoriamente o al primer técnico disponible del nuevo nivel
            $nuevoTecnico = TecnicoDtic::where('nivel', $nuevoNivel)
                ->where('estado', true)
                ->inRandomOrder()
                ->first();

            $updateData = [
                'estado' => 'escalado',
            ];

            if ($nuevoTecnico) {
                $updateData['tecnico_id'] = $nuevoTecnico->user_id;
            }

            $ticket->update($updateData);

            Log::info("ESCALACIÓN AUTOMÁTICA: Ticket {$ticket->codigo_ticket} escalado del Nivel {$nivelActual} al Nivel {$nuevoNivel} por vencimiento de SLA.");
        });
    }
}
