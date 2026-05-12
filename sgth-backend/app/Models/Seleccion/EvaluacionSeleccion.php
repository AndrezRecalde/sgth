<?php

namespace App\Models\Seleccion;

use App\Models\User;
use App\Observers\Seleccion\EvaluacionSeleccionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EvaluacionSeleccionObserver::class)]
class EvaluacionSeleccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evaluaciones_seleccion';

    protected $fillable = [
        'postulante_id',
        'puntaje_meritos',
        'puntaje_oposicion',
        'puntaje_total',
        'observaciones',
        'evaluador_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'puntaje_meritos'   => 'decimal:2',
            'puntaje_oposicion' => 'decimal:2',
            'puntaje_total'     => 'decimal:2',
        ];
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
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
