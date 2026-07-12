<?php

namespace App\Models\Seleccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaCriterio extends Model
{
    protected $table = 'plantilla_criterios';

    protected $fillable = [
        'plantilla_id', 'seccion', 'nombre',
        'descripcion', 'puntaje_maximo',
        'tipo_input', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'puntaje_maximo' => 'decimal:2',
            'orden'          => 'integer',
        ];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaEvaluacion::class, 'plantilla_id');
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(PlantillaOpcion::class, 'plantilla_criterio_id')
            ->orderBy('orden');
    }
}
