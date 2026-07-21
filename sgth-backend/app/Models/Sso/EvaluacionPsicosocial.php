<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Estructura\UnidadAdministrativa;

class EvaluacionPsicosocial extends Model
{
    protected $table = 'evaluaciones_psicosociales';

    protected $fillable = [
        'periodo', 'unidad_administrativa_id', 'codigo_acceso',
        'fecha_apertura', 'fecha_cierre', 'activa', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'date',
            'fecha_cierre' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaPsicosocial::class, 'evaluacion_psicosocial_id');
    }
}
