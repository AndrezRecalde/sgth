<?php

namespace App\Models\Seleccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaOpcion extends Model
{
    protected $table = 'plantilla_opciones';

    protected $fillable = [
        'plantilla_criterio_id', 'etiqueta',
        'puntaje', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'puntaje' => 'decimal:2',
            'orden'   => 'integer',
        ];
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(PlantillaCriterio::class, 'plantilla_criterio_id');
    }
}
