<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaPsicosocial extends Model
{
    protected $table = 'respuestas_psicosociales';

    public $timestamps = false;

    protected $fillable = [
        'evaluacion_psicosocial_id',
        'area_trabajo', 'nivel_instruccion', 'antiguedad', 'rango_edad',
        'autoidentificacion_etnica', 'genero',
        'respuestas', 'puntajes_dimensiones', 'puntaje_global', 'nivel_riesgo_global',
    ];

    protected function casts(): array
    {
        return [
            'respuestas' => 'array',
            'puntajes_dimensiones' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionPsicosocial::class, 'evaluacion_psicosocial_id');
    }
}
