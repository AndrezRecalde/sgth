<?php

namespace App\Models\Seleccion;

use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Seleccion\OnboardingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(OnboardingObserver::class)]
class Onboarding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'postulante_id',
        'servidor_id',
        'documentacion_entregada',
        'induccion_completada',
        'contrato_firmado',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'documentacion_entregada' => 'boolean',
            'induccion_completada'    => 'boolean',
            'contrato_firmado'        => 'boolean',
        ];
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
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
