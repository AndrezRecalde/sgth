<?php

namespace App\Models\Helpdesk;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La categoría de un ticket (D7): de qué se trata y qué subunidad de TIC lo
 * atiende por defecto. La de hardware lleva el equipo, y al resolverse queda
 * el mantenimiento en su ficha.
 */
class CategoriaTicket extends Model
{
    use SoftDeletes;

    protected $table = 'categorias_ticket';

    protected $fillable = ['nombre', 'es_hardware', 'descripcion', 'unidad_tic_id', 'activa'];

    protected function casts(): array
    {
        return ['es_hardware' => 'boolean', 'activa' => 'boolean'];
    }

    public function unidadTic(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_tic_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'categoria_id');
    }
}
