<?php

namespace App\Models\Evaluacion;

use App\Models\User;
use App\Observers\Evaluacion\PlanMejoraObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PlanMejoraObserver::class)]
class PlanMejora extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'planes_mejora';

    protected $fillable = [
        'resultado_id',
        'brechas_identificadas',
        'acciones_mejora',
        'fecha_cumplimiento',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cumplimiento' => 'date',
        ];
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ResultadoEvaluacion::class, 'resultado_id');
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
