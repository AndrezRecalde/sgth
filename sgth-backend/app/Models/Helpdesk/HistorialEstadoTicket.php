<?php

namespace App\Models\Helpdesk;

use App\Enums\EstadoTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un cambio de estado de un ticket: de cuál a cuál, quién, cuándo y por qué. */
class HistorialEstadoTicket extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historial_estados_ticket';

    protected $fillable = ['ticket_id', 'estado_anterior', 'estado_nuevo', 'user_id', 'detalle'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoTicket::class,
            'estado_nuevo'    => EstadoTicket::class,
            'created_at'      => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
