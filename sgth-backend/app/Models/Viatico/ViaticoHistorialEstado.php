<?php

namespace App\Models\Viatico;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un paso en la vida de un viático. Solo se escribe desde
 * `ViaticoEstadoService`: nunca se edita ni se borra.
 */
#[Fillable(['viatico_id', 'estado_anterior', 'estado_nuevo', 'motivo', 'usuario_id'])]
class ViaticoHistorialEstado extends Model
{
    protected $table = 'viatico_historial_estados';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
