<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Estructura\Puesto;
use App\Observers\Sso\RiesgoLaboralObserver;
use App\Enums\NivelDeficienciaRiesgo;
use App\Enums\NivelExposicionRiesgo;
use App\Enums\NivelConsecuenciasRiesgo;
use App\Enums\NivelIntervencionRiesgo;

#[ObservedBy(RiesgoLaboralObserver::class)]
class RiesgoLaboral extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'riesgos_laborales';

    protected $fillable = [
        'puesto_id', 'factor_riesgo_id', 'descripcion',
        'nivel_deficiencia', 'nivel_exposicion', 'nivel_consecuencias',
        'nivel_probabilidad', 'nivel_riesgo_valor', 'nivel_intervencion',
        'medidas_preventivas', 'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'nivel_deficiencia' => NivelDeficienciaRiesgo::class,
            'nivel_exposicion' => NivelExposicionRiesgo::class,
            'nivel_consecuencias' => NivelConsecuenciasRiesgo::class,
            'nivel_intervencion' => NivelIntervencionRiesgo::class,
            'estado' => 'boolean',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function factorRiesgo(): BelongsTo
    {
        return $this->belongsTo(FactorRiesgoCatalogo::class, 'factor_riesgo_id');
    }
}
