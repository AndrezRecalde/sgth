<?php

namespace App\Models\Seleccion;

use App\Enums\EstadoPostulante;
use App\Models\User;
use App\Observers\Seleccion\PostulanteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Seleccion\DocumentoPostulante;

#[ObservedBy(PostulanteObserver::class)]
class Postulante extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'convocatoria_id',
        'cedula',
        'nombres',
        'segundo_nombre',
        'apellidos',
        'segundo_apellido',
        'correo',
        'telefono',
        'genero',
        'estado_civil',
        'fecha_nacimiento',
        'tipo_sangre',
        'tiene_discapacidad',
        'porcentaje_discapacidad',
        'provincia_nacimiento_id',
        'canton_nacimiento_id',
        'cv_ruta',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado'              => EstadoPostulante::class,
            'fecha_nacimiento'    => 'date',
            'tiene_discapacidad'  => 'boolean',
        ];
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(EvaluacionSeleccion::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoPostulante::class);
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
