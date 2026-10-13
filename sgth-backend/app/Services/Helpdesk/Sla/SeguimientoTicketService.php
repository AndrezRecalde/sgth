<?php

namespace App\Services\Helpdesk\Sla;

use App\Enums\EstadoTicket;
use App\Enums\Permiso;
use App\Mail\Helpdesk\TicketSlaMail;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use App\Services\Helpdesk\Tickets\TicketCarga;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lo que pasa solo con el reloj en la mesa de ayuda (docs/diseno-subsistema-
 * tic.md, 4.3; fase 3.2). Lo corre `sgth:helpdesk:seguimiento` cada quince
 * minutos y reemplaza al `SlaService` anterior, que escribía un estado
 * `escalado` que no existía y solo dejaba avisos en el log.
 *
 * - **Por vencer:** al técnico (o a quien reparte, si no tiene), una vez,
 *   cuando le queda una cuarta parte del plazo de resolución.
 * - **Vencido:** sube de nivel (hasta el 3, el proveedor) y avisa al técnico y
 *   a quien reparte, una vez. El estado no cambia: reasignarlo es una decisión
 *   del jefe.
 * - **Cierre automático:** el resuelto que el solicitante no confirmó ni
 *   reabrió en N días hábiles se cierra.
 *
 * Un ticket en espera del usuario no cuenta: su reloj está detenido.
 */
final class SeguimientoTicketService
{
    public function __construct(
        private readonly SlaTicket $sla,
        private readonly CalendarioHabil $calendario,
        private readonly TicketCarga $carga,
    ) {}

    /** @return array{por_vencer: int, vencidos: int, cerrados: int} */
    public function correr(): array
    {
        return [
            'por_vencer' => $this->alertarPorVencer(),
            'vencidos'   => $this->escalarVencidos(),
            'cerrados'   => $this->cerrarResueltos(),
        ];
    }

    public function alertarPorVencer(): int
    {
        $alertados = 0;
        $tickets = Ticket::with('solicitante', 'tecnico.servidor')
            ->whereIn('estado', EstadoTicket::enCurso())
            ->whereNull('pausado_desde')->whereNull('alerta_sla_en')->whereNull('sla_vencido_en')
            ->whereNotNull('fecha_vencimiento_sla')->where('fecha_vencimiento_sla', '>', now())
            ->get();

        foreach ($tickets as $t) {
            $restante = $this->sla->horasRestantes($t);
            if ($restante === null || $restante > $t->sla_resolucion_horas * config('helpdesk.alerta_restante', 0.25)) {
                continue;
            }
            $t->forceFill(['alerta_sla_en' => now()])->save();
            $this->avisar($t, 'por_vencer', $t->tecnico ? collect([$t->tecnico]) : $this->quienesReparten());
            $alertados++;
        }

        return $alertados;
    }

    public function escalarVencidos(): int
    {
        $tickets = Ticket::with('solicitante', 'tecnico.servidor')->vencidos()->whereNull('sla_vencido_en')->get();

        foreach ($tickets as $t) {
            DB::transaction(function () use ($t) {
                $nivel = min($t->nivel + 1, 3);
                $subio = $nivel > $t->nivel;
                $t->forceFill(['sla_vencido_en' => now(), 'nivel' => $nivel])->save();
                $this->carga->anotar($t, $t->estado, $subio
                    ? "SLA vencido: subió al nivel {$nivel}."
                    : 'SLA vencido en el nivel 3.');
            });
            $this->avisar($t, 'vencido', $this->quienesReparten()->push($t->tecnico)->filter()->unique('id'));
        }

        return $tickets->count();
    }

    public function cerrarResueltos(): int
    {
        $dias = (int) config('helpdesk.cierre_automatico_dias_habiles', 3);
        $cerrados = 0;

        foreach (Ticket::where('estado', EstadoTicket::RESUELTO)->whereNotNull('resuelto_en')->get() as $t) {
            if ($this->calendario->sumarDias($t->resuelto_en, $dias)->isFuture()) {
                continue;
            }

            DB::transaction(function () use ($t, $dias) {
                $t->update(['estado' => EstadoTicket::CERRADO, 'fecha_cierre' => now()]);
                $this->carga->anotar($t, EstadoTicket::RESUELTO, "Se cerró solo: el usuario no respondió en {$dias} días hábiles.");
            });
            $cerrados++;
        }

        return $cerrados;
    }

    /** @return Collection<int, User> */
    private function quienesReparten(): Collection
    {
        return User::permission(Permiso::ASIGNAR_TICKET->value)->where('activo', true)->with('servidor')->get();
    }

    /** @param  Collection<int, User>  $destinatarios */
    private function avisar(Ticket $ticket, string $aviso, Collection $destinatarios): void
    {
        foreach ($destinatarios->pluck('email')->filter()->unique() as $correo) {
            try {
                Mail::to($correo)->send(new TicketSlaMail($ticket, $aviso));
            } catch (\Throwable $e) {
                Log::warning('No salió la alerta del SLA', ['ticket' => $ticket->codigo_ticket, 'aviso' => $aviso, 'motivo' => $e->getMessage()]);
            }
        }
    }
}
