<?php

namespace App\Models\Dispensario;

use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Sso\AccidenteTrabajo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FichaSaludOcupacional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fichas_salud_ocupacional';

    protected $fillable = [
        'servidor_id', 'evaluador_id', 'accidente_trabajo_id',
        'numero_archivo', 'fecha_evaluacion',
        'tipo_ficha', 'puesto_trabajo', 'puesto_trabajo_ciuo',
        'fecha_ingreso_trabajo',
        'grupo_embarazada', 'grupo_discapacidad',
        'porcentaje_discapacidad',
        'aptitud', 'restricciones', 'observaciones',
        'enfermedad_actual', 'recomendaciones', 'tratamiento',
        'condicion_relacionada_trabajo', 'observacion_retiro',
        'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_evaluacion'             => 'date',
            'fecha_ingreso_trabajo'        => 'date',
            'grupo_embarazada'             => 'boolean',
            'grupo_discapacidad'           => 'boolean',
            'condicion_relacionada_trabajo'=> 'boolean',
            'estado'                       => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function accidenteTrabajo(): BelongsTo
    {
        return $this->belongsTo(AccidenteTrabajo::class);
    }

    public function constantesVitales(): HasOne
    {
        return $this->hasOne(FemoConstantesVitales::class, 'ficha_id');
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(FemoAntecedente::class, 'ficha_id');
    }

    public function factoresRiesgo(): HasMany
    {
        return $this->hasMany(FemoFactorRiesgo::class, 'ficha_id');
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(FemoDiagnostico::class, 'ficha_id')
            ->orderBy('orden');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(FemoExamen::class, 'ficha_id');
    }

    public function empleosAnteriores(): HasMany
    {
        return $this->hasMany(FemoEmpleoAnterior::class, 'ficha_id');
    }
}
