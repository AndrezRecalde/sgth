<?php

namespace App\Models\Evaluacion;

use App\Enums\EstadoEvaluacion;
use App\Models\User;
use App\Observers\Evaluacion\EvaluacionDesempenoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EvaluacionDesempenoObserver::class)]
class EvaluacionDesempeno extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evaluaciones_desempeno';

    protected $fillable = [
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'estado'       => EstadoEvaluacion::class,
        ];
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(CriterioEvaluacion::class, 'evaluacion_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoEvaluacion::class, 'evaluacion_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
