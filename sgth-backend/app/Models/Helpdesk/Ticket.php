<?php namespace App\Models\Helpdesk; use App\Enums\Permiso; use Illuminate\Database\Eloquent\Builder; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; use App\Models\User; use App\Models\InventarioTi\BienInformatico; use App\Observers\Helpdesk\TicketObserver; use Illuminate\Database\Eloquent\Attributes\ObservedBy; #[ObservedBy(TicketObserver::class)] class Ticket extends Model { use SoftDeletes; protected $table = 'tickets'; protected $fillable = ['codigo_ticket', 'solicitante_id', 'tipo_ticket', 'categoria_id', 'sla_id', 'estado', 'asunto', 'descripcion', 'bien_informatico_id', 'tecnico_id', 'fecha_vencimiento_sla', 'fecha_cierre']; protected function casts(): array { return ['fecha_vencimiento_sla' => 'datetime', 'fecha_cierre' => 'datetime']; } public function categoria() { return $this->belongsTo(CategoriaTicket::class); }
    public function solicitante() { return $this->belongsTo(Servidor::class); } public function sla() { return $this->belongsTo(Sla::class); } public function bien() { return $this->belongsTo(BienInformatico::class, 'bien_informatico_id'); } public function tecnico() { return $this->belongsTo(User::class, 'tecnico_id'); }

    /**
     * Los comentarios del ticket, del más antiguo al más reciente.
     *
     * El detalle del ticket ya los cargaba con `with('comentarios')`, pero la
     * relación no existía: la pantalla de un ticket reventaba en cuanto había
     * un ticket que mostrar. No se veía en las pruebas sobre base vacía porque
     * Eloquent solo resuelve la relación si la consulta devuelve algo.
     */
    public function comentarios() { return $this->hasMany(ComentarioTicket::class)->oldest(); }

    /**
     * Los tickets que el usuario puede ver, con la regla de `TicketPolicy::view`:
     * todos con `ver-tickets-todos`; los de su cola y los sin asignar con
     * `gestionar-tickets`; y siempre los que abrió él.
     *
     * Antes el listado no tenía alcance: cualquier usuario recibía los tickets
     * de toda la institución.
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