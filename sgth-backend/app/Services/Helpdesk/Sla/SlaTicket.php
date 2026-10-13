<?php

namespace App\Services\Helpdesk\Sla;

use App\Models\Helpdesk\Sla;
use App\Models\Helpdesk\Ticket;
use Carbon\CarbonInterface;

/**
 * El SLA de un ticket (docs/diseno-subsistema-tic.md, 4.3; fase 3.2): cuánto
 * le toca por su categoría y prioridad, cuándo vencen la primera respuesta y
 * la resolución en horas hábiles, y la pausa mientras espera al usuario.
 *
 * Las horas se guardan en el ticket al calcularse: cambiar la tabla de SLA
 * después no mueve los plazos de los que ya están en curso.
 */
final class SlaTicket
{
    public function __construct(private readonly CalendarioHabil $calendario) {}

    /**
     * Las horas que le tocan: la fila de su categoría y prioridad, si no la
     * general de la prioridad, y si no la de `config/helpdesk.php`.
     *
     * @return array{sla_id: ?int, respuesta: float, resolucion: float}
     */
    public function horas(?int $categoriaId, string $prioridad): array
    {
        $fila = ($categoriaId ? Sla::where('categoria_id', $categoriaId)->where('prioridad', $prioridad)->first() : null)
            ?? Sla::whereNull('categoria_id')->where('prioridad', $prioridad)->first();

        if ($fila) {
            return [
                'sla_id'     => $fila->id,
                'respuesta'  => (float) ($fila->tiempo_respuesta_horas ?? $fila->tiempo_resolucion_horas),
                'resolucion' => (float) $fila->tiempo_resolucion_horas,
            ];
        }

        $base = config("helpdesk.sla.{$prioridad}") ?? config('helpdesk.sla.media');

        return ['sla_id' => null, 'respuesta' => (float) $base['respuesta'], 'resolucion' => (float) $base['resolucion']];
    }

    /**
     * Fija o recalcula los plazos. Corre desde `$desde` (el alta, o la
     * reapertura) y suma lo que estuvo en pausa. No guarda: lo hace quien
     * llama, junto con el resto del cambio.
     */
    public function aplicar(Ticket $ticket, ?CarbonInterface $desde = null): void
    {
        $horas = $this->horas($ticket->categoria_id, $ticket->prioridad->value);
        $inicio = $desde ?? $ticket->created_at ?? now();
        $pausa = $ticket->minutos_pausados / 60;

        $ticket->forceFill([
            'sla_id'                => $horas['sla_id'],
            'sla_respuesta_horas'   => $horas['respuesta'],
            'sla_resolucion_horas'  => $horas['resolucion'],
            'vence_respuesta_en'    => $this->calendario->sumarHoras($inicio, $horas['respuesta']),
            'fecha_vencimiento_sla' => $this->calendario->sumarHoras($inicio, $horas['resolucion'] + $pausa),
        ]);
    }

    /** Entra en espera del usuario: el reloj se detiene. */
    public function pausar(Ticket $ticket): void
    {
        $ticket->forceFill(['pausado_desde' => now()]);
    }

    /** Sale de la espera: lo pausado se suma y el vencimiento se corre. */
    public function reanudar(Ticket $ticket): void
    {
        if (! $ticket->pausado_desde) {
            return;
        }

        $horas = $this->calendario->horasEntre($ticket->pausado_desde, now());
        $ticket->forceFill([
            'minutos_pausados'      => $ticket->minutos_pausados + (int) round($horas * 60),
            'pausado_desde'         => null,
            'fecha_vencimiento_sla' => $ticket->fecha_vencimiento_sla
                ? $this->calendario->sumarHoras($ticket->fecha_vencimiento_sla, $horas)
                : null,
        ]);
    }

    /** La primera vez que TIC responde: tomarlo, preguntar o escribirle. */
    public function marcarRespuesta(Ticket $ticket): void
    {
        if (! $ticket->primera_respuesta_en) {
            $ticket->forceFill(['primera_respuesta_en' => now()]);
        }
    }

    /** Las horas hábiles que le quedan para resolverse (negativas si ya venció). */
    public function horasRestantes(Ticket $ticket): ?float
    {
        if (! $ticket->fecha_vencimiento_sla) {
            return null;
        }

        $desde = $ticket->pausado_desde ?? now();

        return $ticket->fecha_vencimiento_sla->gte($desde)
            ? $this->calendario->horasEntre($desde, $ticket->fecha_vencimiento_sla)
            : -$this->calendario->horasEntre($ticket->fecha_vencimiento_sla, $desde);
    }
}
