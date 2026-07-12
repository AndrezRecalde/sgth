<?php

namespace App\Models\Seleccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcionCriterio extends Model
{
    protected $table = 'opciones_criterio';

    protected $fillable = [
        'criterio_id', 'etiqueta', 'puntaje', 'orden',
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
        return $this->belongsTo(CriterioEvaluacion::class, 'criterio_id');
    }
}
