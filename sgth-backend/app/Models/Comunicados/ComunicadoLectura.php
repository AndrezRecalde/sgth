<?php

namespace App\Models\Comunicados;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Que un usuario abrió una publicación y, si se le pidió, que confirmó
 * haberla leído (docs/diseno-noticias-comunicados.md, 3.4). La confirmación
 * no se deshace.
 */
class ComunicadoLectura extends Model
{
    public $timestamps = false;

    protected $table = 'comunicado_lecturas';

    protected $fillable = ['comunicado_id', 'user_id', 'leido_at', 'confirmado_at'];

    protected function casts(): array
    {
        return [
            'leido_at'      => 'datetime',
            'confirmado_at' => 'datetime',
        ];
    }

    public function comunicado(): BelongsTo
    {
        return $this->belongsTo(Comunicado::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
