<?php

namespace App\Models\Evaluacion;

use App\Enums\CalificacionMrl;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Evaluacion\ResultadoEvaluacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ResultadoEvaluacionObserver::class)]
class ResultadoEvaluacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'resultados_evaluacion';

    protected $fillable = [
        'evaluacion_id',
        'servidor_id',
        'evaluador_id',
        'calificacion_cuantitativa',
        'calificacion_cualitativa',
        'observaciones',
        'retroalimentacion_fecha',
        'apelacion_estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'calificacion_cuantitativa' => 'decimal:2',
            'calificacion_cualitativa'  => CalificacionMrl::class,
            'retroalimentacion_fecha'   => 'datetime',
        ];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionDesempeno::class, 'evaluacion_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function planMejora(): HasOne
    {
        return $this->hasOne(PlanMejora::class, 'resultado_id');
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
