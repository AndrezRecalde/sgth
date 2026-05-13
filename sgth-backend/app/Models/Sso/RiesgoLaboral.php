<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Estructura\Puesto;
use App\Observers\Sso\RiesgoLaboralObserver;

#[ObservedBy(RiesgoLaboralObserver::class)]
class RiesgoLaboral extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'riesgos_laborales';

    protected $fillable = [
        'puesto_id', 'tipo_riesgo', 'descripcion', 'probabilidad',
        'consecuencia', 'nivel_riesgo', 'medidas_preventivas', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }
}
