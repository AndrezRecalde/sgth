<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; use App\Models\User; use App\Models\InventarioTi\BienInformatico; use App\Observers\Helpdesk\TicketObserver; use Illuminate\Database\Eloquent\Attributes\ObservedBy; #[ObservedBy(TicketObserver::class)] class Ticket extends Model { use SoftDeletes; protected $table = 'tickets'; protected $fillable = ['codigo_ticket', 'solicitante_id', 'tipo_ticket', 'categoria_id', 'sla_id', 'estado', 'asunto', 'descripcion', 'bien_informatico_id', 'tecnico_id', 'fecha_vencimiento_sla', 'fecha_cierre']; protected function casts(): array { return ['fecha_vencimiento_sla' => 'datetime', 'fecha_cierre' => 'datetime']; } public function categoria() { return $this->belongsTo(CategoriaTicket::class); }
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
}