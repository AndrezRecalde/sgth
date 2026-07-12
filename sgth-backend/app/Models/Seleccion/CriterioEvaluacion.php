<?php

namespace App\Models\Seleccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioEvaluacion extends Model
{
    protected $table = 'criterios_evaluacion';

    protected $fillable = [
        'convocatoria_id', 'seccion', 'nombre',
        'descripcion', 'puntaje_maximo',
        'tipo_input', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'puntaje_maximo' => 'decimal:2',
            'activo'         => 'boolean',
            'orden'          => 'integer',
        ];
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionCriterio::class, 'criterio_id')
            ->orderBy('orden');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(CalificacionPostulante::class, 'criterio_id');
    }
}
