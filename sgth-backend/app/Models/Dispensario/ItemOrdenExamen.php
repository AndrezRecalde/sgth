<?php

namespace App\Models\Dispensario;

use App\Enums\EstadoItemOrdenExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un examen de una orden, con el dato que completó el médico si lo lleva. */
class ItemOrdenExamen extends Model
{
    protected $table = 'orden_examen_items';

    protected $fillable = [
        'orden_examen_id', 'catalogo_examen_id', 'nota',
        'estado', 'motivo_no_realizado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoItemOrdenExamen::class,
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenExamen::class, 'orden_examen_id');
    }

    public function examen(): BelongsTo
    {
        return $this->belongsTo(CatalogoExamen::class, 'catalogo_examen_id');
    }
}
