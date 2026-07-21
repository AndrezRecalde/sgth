<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaAssist extends Model
{
    protected $table = 'respuestas_assist';

    public $timestamps = false;

    protected $fillable = [
        'evaluacion_assist_id',
        'respuestas', 'puntajes', 'niveles_riesgo', 'nivel_riesgo_maximo', 'uso_inyectable',
    ];

    protected function casts(): array
    {
        return [
            'respuestas' => 'array',
            'puntajes' => 'array',
            'niveles_riesgo' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionAssist::class, 'evaluacion_assist_id');
    }
}
