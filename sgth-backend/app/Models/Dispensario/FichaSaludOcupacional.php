<?php

namespace App\Models\Dispensario;

use App\Enums\AptitudMedica;
use App\Enums\TipoFichaFemo;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Postulante;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FichaSaludOcupacional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fichas_salud_ocupacional';

    protected $fillable = [
        'servidor_id', 'postulante_id', 'evaluador_id', 'accidente_trabajo_id',
        'numero_archivo', 'fecha_evaluacion',
        'tipo_ficha', 'puesto_trabajo', 'puesto_trabajo_ciuo',
        'fecha_ingreso_trabajo',
        'grupo_embarazada', 'grupo_discapacidad',
        'porcentaje_discapacidad',
        'aptitud', 'restricciones', 'observaciones',
        'enfermedad_actual', 'recomendaciones', 'tratamiento',
        'condicion_relacionada_trabajo', 'observacion_retiro',
        'actividad_extralaboral_descripcion', 'actividad_extralaboral_fecha',
        'se_realiza_evaluacion_retiro',
        'actividad_fisica_cual', 'actividad_fisica_tiempo',
        'medicacion_habitual_cual', 'medicacion_habitual_cantidad',
        'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_evaluacion' => 'date',
            'fecha_ingreso_trabajo' => 'date',
            'grupo_embarazada' => 'boolean',
            'grupo_discapacidad' => 'boolean',
            'aptitud' => AptitudMedica::class,
            'tipo_ficha' => TipoFichaFemo::class,
            'condicion_relacionada_trabajo' => 'boolean',
            'actividad_extralaboral_fecha' => 'date',
            'se_realiza_evaluacion_retiro' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
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

    public function actividades(): HasMany
    {
        return $this->hasMany(FemoFichaActividad::class, 'ficha_id')->orderBy('orden');
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

    public function examenFisico(): HasMany
    {
        return $this->hasMany(FemoExamenFisico::class, 'ficha_id')->orderBy('region');
    }

    public function antecedenteReproductivo(): HasOne
    {
        return $this->hasOne(FemoAntecedenteReproductivo::class, 'ficha_id');
    }

    public function consumoSustancias(): HasMany
    {
        return $this->hasMany(FemoConsumoSustancia::class, 'ficha_id');
    }
}
