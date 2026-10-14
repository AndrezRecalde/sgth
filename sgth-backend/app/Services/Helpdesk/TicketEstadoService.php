<?php

namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\TicketEstadoServiceInterface;
use App\Contracts\InventarioTi\MantenimientoServiceInterface;
use App\Enums\EstadoTicket;
use App\Enums\Permiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\BienInformatico;
use App\Models\User;
use App\Services\Helpdesk\Sla\SlaTicket;
use App\Services\Helpdesk\Tickets\AvisoTicketService;
use App\Services\Helpdesk\Tickets\TicketCarga;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El trámite de un ticket (docs/diseno-subsistema-tic.md, 4.3; fase 3.1):
 *
 *   abierto ─asignar─► asignado ─tomar─► en_proceso ─resolver─► resuelto ─confirmar─► cerrado
 *                          ▲                │  ▲                     │
 *                          └──escalar───────┘  └── reanudar ◄─ pendiente_usuario
 *                          └──────────── reabrir ◄────────────────────┘
 *   y anulado, por duplicado o mal registrado, mientras no esté resuelto.
 *
 * Antes `cambiarEstado` aceptaba cualquier texto y el SLA escribía estados que
 * no existían. Cada cambio deja su historial: quién, cuándo y por qué. Desde
 * la fase 3.5 avisa por correo al técnico (asignado, reabierto) y al
 * solicitante (le preguntan, se resolvió).
 */
final class TicketEstadoService implements TicketEstadoServiceInterface
{
    /** De qué estado se puede pasar a cuáles. */
    public const TRANSICIONES = [
        'abierto'           => ['asignado', 'en_proceso', 'anulado'],
        'asignado'          => ['asignado', 'en_proceso', 'abierto', 'anulado'],
        'en_proceso'        => ['pendiente_usuario', 'resuelto', 'asignado', 'abierto', 'anulado'],
        'pendiente_usuario' => ['en_proceso', 'resuelto', 'anulado'],
        'resuelto'          => ['cerrado', 'asignado', 'abierto'],
        'cerrado'           => [],
        'anulado'           => [],
    ];

    public function __construct(
        private readonly TicketCarga $carga,
        private readonly MantenimientoServiceInterface $mantenimientos,
        private readonly SlaTicket $sla,
        private readonly AvisoTicketService $avisos,
    ) {}

    public function asignar(Ticket $ticket, array $datos): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::ASIGNADO, function (Ticket $t) use ($datos) {
            $tecnico = isset($datos['tecnico_id']) ? $this->tecnico((int) $datos['tecnico_id']) : null;
            $t->update(array_filter([
                'tecnico_id'    => $tecnico?->id,
                'unidad_tic_id' => $datos['unidad_tic_id'] ?? null,
                'prioridad'     => $datos['prioridad'] ?? null,
            ], fn ($v) => $v !== null));

            if (! $t->tecnico_id) {
                throw ValidationException::withMessages(['tecnico_id' => 'Elija el técnico que lo atiende.']);
            }
            // Otra prioridad, otro plazo: se recalcula desde el alta.
            if ($t->wasChanged('prioridad')) {
                $this->sla->aplicar($t);
            }

            return 'Asignado a ' . ($tecnico ?? $t->tecnico)->nombre_completo . '.';
        }, 'asignado');
    }

    public function tomar(Ticket $ticket, User $tecnico): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::EN_PROCESO, function (Ticket $t) use ($tecnico) {
            if ($t->tecnico_id && (int) $t->tecnico_id !== (int) $tecnico->id && ! $tecnico->can(Permiso::ASIGNAR_TICKET->value)) {
                throw new ReglaNegocioException('Este ticket lo tiene otro técnico.');
            }
            $t->forceFill(['tecnico_id' => $tecnico->id]);
            $this->sla->marcarRespuesta($t);

            return null;
        });
    }

    public function pedirInformacion(Ticket $ticket, User $tecnico, string $pregunta): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::PENDIENTE_USUARIO, function (Ticket $t) use ($tecnico, $pregunta) {
            ComentarioTicket::create(['ticket_id' => $t->id, 'user_id' => $tecnico->id, 'comentario' => $pregunta, 'es_interno' => false]);
            $this->sla->marcarRespuesta($t);

            return 'Se pidió información al usuario; el SLA queda en pausa.';
        }, 'pendiente');
    }

    public function reanudar(Ticket $ticket, User $user): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::EN_PROCESO, fn () => 'El usuario respondió.');
    }

    public function resolver(Ticket $ticket, User $tecnico, array $datos): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::RESUELTO, function (Ticket $t) use ($tecnico, $datos) {
            $t->forceFill(['solucion' => $datos['solucion'], 'resuelto_en' => now(), 'resuelto_por' => $tecnico->id]);
            $this->sla->marcarRespuesta($t);
            $t->loadMissing('categoria');

            // El ticket de hardware con equipo deja el correctivo en la ficha (4.3).
            $registrar = $datos['registrar_mantenimiento'] ?? ($t->categoria?->es_hardware ?? false);
            if ($t->bien_informatico_id && $registrar) {
                $this->mantenimientos->registrar([
                    'bien_informatico_id' => $t->bien_informatico_id,
                    'ticket_id'           => $t->id,
                    'tipo_mantenimiento'  => 'correctivo',
                    'realizado_por'       => 'tic',
                    'fecha_mantenimiento' => now()->toDateString(),
                    'tecnico_id'          => $tecnico->id,
                    'descripcion'         => "{$t->codigo_ticket}: {$datos['solucion']}",
                    'estado_equipo'       => $datos['estado_equipo'] ?? null,
                ]);

                return 'Resuelto; el mantenimiento quedó en la ficha del equipo.';
            }

            return null;
        }, 'resuelto');
    }

    public function confirmar(Ticket $ticket, ?int $calificacion, ?string $comentario): Ticket
    {
        return $this->pasar($ticket, EstadoTicket::CERRADO, function (Ticket $t) use ($calificacion, $comentario) {
            $t->update(['fecha_cierre' => now()]);
            if ($calificacion) {
                EncuestaSatisfaccion::create([
                    'ticket_id' => $t->id, 'calificacion' => $calificacion, 'comentarios' => $comentario, 'fecha_respuesta' => now(),
                ]);
            }

            return $calificacion ? "El usuario confirmó la solución y la calificó con {$calificacion} de 5." : 'El usuario confirmó la solución.';
        });
    }

    public function reabrir(Ticket $ticket, User $user, string $motivo): Ticket
    {
        // Vuelve a quien lo resolvió; si ya no está, a la cola.
        $destino = $ticket->tecnico_id ? EstadoTicket::ASIGNADO : EstadoTicket::ABIERTO;

        return $this->pasar($ticket, $destino, function (Ticket $t) use ($user, $motivo) {
            $t->forceFill([
                'reabierto_veces' => $t->reabierto_veces + 1, 'resuelto_en' => null, 'resuelto_por' => null,
                'minutos_pausados' => 0, 'alerta_sla_en' => null, 'sla_vencido_en' => null,
            ]);
            // Corre un plazo nuevo desde que se reabre.
            $this->sla->aplicar($t, now());
            ComentarioTicket::create(['ticket_id' => $t->id, 'user_id' => $user->id, 'comentario' => "No quedó resuelto: {$motivo}", 'es_interno' => false]);

            return $motivo;
        }, $ticket->tecnico_id ? 'reabierto' : null);
    }

    public function escalar(Ticket $ticket, int $nivel, string $motivo, ?int $tecnicoId): Ticket
    {
        $destino = $tecnicoId ? EstadoTicket::ASIGNADO : EstadoTicket::ABIERTO;

        return $this->pasar($ticket, $destino, function (Ticket $t) use ($nivel, $motivo, $tecnicoId) {
            if ($nivel <= $t->nivel) {
                throw ValidationException::withMessages(['nivel' => "El ticket ya está en el nivel {$t->nivel}: se escala a uno mayor."]);
            }
            $tecnico = $tecnicoId ? $this->tecnico($tecnicoId) : null;
            $t->update(['nivel' => $nivel, 'tecnico_id' => $tecnico?->id]);

            return "Escalado al nivel {$nivel}" . ($tecnico ? " con {$tecnico->nombre_completo}" : ', de vuelta a la cola') . ": {$motivo}";
        }, $tecnicoId ? 'asignado' : null);
    }

    public function anular(Ticket $ticket, User $user, string $motivo): Ticket
    {
        // El solicitante solo anula el suyo mientras nadie lo ha tomado.
        if (! $user->can(Permiso::ASIGNAR_TICKET->value) && $ticket->estado !== EstadoTicket::ABIERTO) {
            throw new ReglaNegocioException('El ticket ya lo está atendiendo TIC: pida que lo anulen allá, o responda en la conversación.');
        }

        return $this->pasar($ticket, EstadoTicket::ANULADO, function (Ticket $t) use ($user, $motivo) {
            $t->update(['anulado_en' => now(), 'anulado_por' => $user->id, 'motivo_anulacion' => $motivo]);

            return $motivo;
        });
    }

    public function vincularBien(Ticket $ticket, ?int $bienId): Ticket
    {
        if ($ticket->estado->final()) {
            throw new ReglaNegocioException('El ticket ya está cerrado.');
        }
        if ($bienId && ! BienInformatico::whereKey($bienId)->exists()) {
            throw ValidationException::withMessages(['bien_informatico_id' => 'Ese equipo no está en el inventario.']);
        }

        $ticket->update(['bien_informatico_id' => $bienId]);

        return $this->carga->cargar($ticket->id);
    }

    /**
     * Bloquea el ticket, comprueba que la transición exista, aplica el cambio
     * y lo anota. `$cambio` devuelve el detalle del historial.
     */
    private function pasar(Ticket $ticket, EstadoTicket $destino, callable $cambio, ?string $aviso = null): Ticket
    {
        DB::transaction(function () use ($ticket, $destino, $cambio) {
            $t = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $anterior = $t->estado;

            if (! in_array($destino->value, self::TRANSICIONES[$anterior->value], true)) {
                throw new ReglaNegocioException(
                    "El ticket {$t->codigo_ticket} está " . mb_strtolower($anterior->etiqueta()) . ': no puede pasar a ' . mb_strtolower($destino->etiqueta()) . '.'
                );
            }

            $detalle = $cambio($t);
            // Esperando al usuario, el reloj del SLA se detiene (4.3).
            if ($anterior === EstadoTicket::PENDIENTE_USUARIO && $destino !== EstadoTicket::PENDIENTE_USUARIO) {
                $this->sla->reanudar($t);
            } elseif ($destino === EstadoTicket::PENDIENTE_USUARIO) {
                $this->sla->pausar($t);
            }
            $t->update(['estado' => $destino]);
            $this->carga->anotar($t, $anterior, $detalle);
        });

        $resultado = $this->carga->cargar($ticket->id);
        // Después de confirmar: un correo no sale de un cambio que se deshizo.
        if ($aviso) {
            $this->avisos->enviar($resultado, $aviso);
        }

        return $resultado;
    }

    /** Solo atiende quien gestiona tickets. */
    private function tecnico(int $id): User
    {
        $tecnico = User::where('activo', true)->find($id);
        if (! $tecnico || ! $tecnico->can(Permiso::GESTIONAR_TICKETS->value)) {
            throw ValidationException::withMessages(['tecnico_id' => 'Ese usuario no atiende tickets de TIC.']);
        }

        return $tecnico;
    }
}
