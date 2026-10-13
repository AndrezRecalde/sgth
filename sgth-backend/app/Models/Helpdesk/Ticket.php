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
