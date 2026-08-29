<?php

namespace App\Models\Seleccion;

use App\Enums\EstadoPostulante;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\Servidor;
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
        // En los contenedores express el puesto lo trae el aspirante: el
        // contenedor agrupa por modalidad, no por vacante.
        'puesto_id',
        'fecha_inscripcion',
        'servidor_id',
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
            'fecha_inscripcion'   => 'date',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /**
     * Puesto al que aspira: el propio en los contenedores express, el de la
     * convocatoria en un concurso formal.
     */
    public function puestoEfectivo(): ?Puesto
    {
        return $this->puesto ?? $this->convocatoria?->puesto;
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class);
    }

    /**
     * Poblado en la inscripción (PostulanteController::store()) cuando la
     * cédula ya coincide con un Servidor existente — candidato interno.
     */
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(EvaluacionSeleccion::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    /**
     * Última solicitud de certificación médica del aspirante.
     *
     * Reclutamiento la necesita para saber en qué punto va el trámite médico:
     * el dictamen decide si ya se puede confirmar la incorporación, y ese paso
     * lo ejecuta Talento Humano, no el dispensario.
     */
    public function solicitudCertificacion(): HasOne
    {
        return $this->hasOne(SolicitudCertificacionMedica::class)->latestOfMany();
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
