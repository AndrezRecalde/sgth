<?php

namespace App\Models\Evaluacion;

use App\Models\User;
use App\Observers\Evaluacion\CriterioEvaluacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(CriterioEvaluacionObserver::class)]
class CriterioEvaluacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'criterios_evaluacion';

    protected $fillable = [
        'evaluacion_id',
        'nombre',
        'descripcion',
        'peso_porcentual',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'peso_porcentual' => 'decimal:2',
        ];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionDesempeno::class, 'evaluacion_id');
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
