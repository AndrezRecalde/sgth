<?php

namespace App\Models\Helpdesk;

use App\Enums\CanalTicket;
use App\Enums\EstadoTicket;
use App\Enums\Permiso;
use App\Enums\PrioridadTicket;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\User;
use App\Observers\Helpdesk\TicketObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un ticket de la mesa de ayuda (docs/diseno-subsistema-tic.md, 4.2 y 4.3):
 * quién lo abrió, qué pasa, quién lo atiende y en qué subunidad de TIC, y su
 * trámite. `codigo_ticket` es el folio `TIC-AAAA-00001`.
 */
#[ObservedBy(TicketObserver::class)]
class Ticket extends Model
{
    use SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'codigo_ticket', 'solicitante_id', 'tipo_ticket', 'categoria_id', 'sla_id', 'estado', 'prioridad', 'canal',
        'asunto', 'descripcion', 'bien_informatico_id', 'tecnico_id', 'unidad_tic_id', 'nivel', 'registrado_por',
        'solucion', 'resuelto_en', 'resuelto_por', 'reabierto_veces',
        'anulado_en', 'anulado_por', 'motivo_anulacion', 'fecha_vencimiento_sla', 'fecha_cierre',
        'sla_respuesta_horas', 'sla_resolucion_horas', 'vence_respuesta_en', 'primera_respuesta_en',
        'pausado_desde', 'minutos_pausados', 'alerta_sla_en', 'sla_vencido_en',
    ];

    protected function casts(): array
    {
        return [
            'estado'                => EstadoTicket::class,
            'prioridad'             => PrioridadTicket::class,
            'canal'                 => CanalTicket::class,
            'nivel'                 => 'integer',
            'reabierto_veces'       => 'integer',
            'resuelto_en'           => 'datetime',
            'anulado_en'            => 'datetime',
            'fecha_vencimiento_sla' => 'datetime',
            'fecha_cierre'          => 'datetime',
            'sla_respuesta_horas'   => 'float',
            'sla_resolucion_horas'  => 'float',
            'vence_respuesta_en'    => 'datetime',
            'primera_respuesta_en'  => 'datetime',
            'pausado_desde'         => 'datetime',
            'minutos_pausados'      => 'integer',
            'alerta_sla_en'         => 'datetime',
            'sla_vencido_en'        => 'datetime',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaTicket::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(Sla::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /** La subunidad de TIC que atiende (D6). */
    public function unidadTic(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_tic_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Los comentarios del ticket, del más antiguo al más reciente.
     *
     * El detalle del ticket ya los cargaba con `with('comentarios')`, pero la
     * relación no existía: la pantalla de un ticket reventaba en cuanto había
     * un ticket que mostrar.
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(ComentarioTicket::class)->oldest()->orderBy('id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstadoTicket::class)->orderBy('id');
    }

    /** La calificación del solicitante al confirmar la solución. */
    public function encuesta(): HasOne
    {
        return $this->hasOne(EncuestaSatisfaccion::class)->latestOfMany();
    }

    /**
     * Cómo va el SLA (fase 3.2). En curso: `en_plazo`, `por_vencer` (salió la
     * alerta), `vencido` o `pausado` (esperando al usuario). Resuelto o
     * cerrado: `cumplido` o `incumplido`, según cuándo se resolvió. Anulado o
     * sin plazo: null.
     */
    public function estadoSla(): ?string
    {
        if (! $this->fecha_vencimiento_sla || $this->estado === EstadoTicket::ANULADO) {
            return null;
        }

        if (in_array($this->estado, [EstadoTicket::RESUELTO, EstadoTicket::CERRADO], true)) {
            $resuelto = $this->resuelto_en ?? $this->fecha_cierre;

            return $resuelto && $resuelto->lte($this->fecha_vencimiento_sla) ? 'cumplido' : 'incumplido';
        }

        return match (true) {
            $this->pausado_desde !== null => 'pausado',
            $this->sla_vencido_en !== null || $this->fecha_vencimiento_sla->isPast() => 'vencido',
            $this->alerta_sla_en !== null => 'por_vencer',
            default => 'en_plazo',
        };
    }

    /** En curso, sin pausa y con el plazo de resolución pasado. */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->whereIn('estado', EstadoTicket::enCurso())
            ->whereNull('pausado_desde')
            ->where('fecha_vencimiento_sla', '<', now());
    }

    /**
     * Los tickets que el usuario puede ver, con la regla de `TicketPolicy::view`:
     * todos con `ver-tickets-todos`; los de su cola y los sin asignar con
     * `gestionar-tickets`; y siempre los que abrió él.
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        if ($user->can(Permiso::VER_TICKETS_TODOS->value)) {
            return $query;
        }

        $atiende = $user->can(Permiso::GESTIONAR_TICKETS->value);
        $propios = $user->servidor_id !== null && $user->can(Permiso::VER_TICKET_PROPIO->value);

        if (! $atiende && ! $propios) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user, $atiende, $propios) {
            if ($atiende) {
                $q->orWhereNull('tecnico_id')->orWhere('tecnico_id', $user->id);
            }
            if ($propios) {
                $q->orWhere('solicitante_id', $user->servidor_id);
            }
        });
    }
}
