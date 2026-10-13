<?php

namespace App\Models\Helpdesk;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un mensaje de la conversación de un ticket, con su archivo si lo lleva. Las
 * notas internas (`es_interno`) son entre quienes atienden: el solicitante no
 * las ve.
 */
class ComentarioTicket extends Model
{
    use SoftDeletes;

    protected $table = 'comentarios_ticket';

    protected $fillable = ['ticket_id', 'user_id', 'comentario', 'es_interno', 'archivo_ruta', 'archivo_nombre', 'archivo_mime'];

    protected $hidden = ['archivo_ruta'];

    protected function casts(): array
    {
        return ['es_interno' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
