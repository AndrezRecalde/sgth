<?php

namespace App\Models\Helpdesk;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un plazo de atención en horas hábiles (fase 3.2): sin categoría es la regla
 * general de su prioridad; con categoría, la afina (D7).
 */
class Sla extends Model
{
    use SoftDeletes;

    protected $table = 'slas';

    protected $fillable = ['prioridad', 'categoria_id', 'tiempo_resolucion_horas', 'tiempo_respuesta_horas'];

    protected function casts(): array
    {
        return ['tiempo_resolucion_horas' => 'float', 'tiempo_respuesta_horas' => 'float'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaTicket::class);
    }
}
