<?php
namespace App\Models\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Observers\PuestoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(PuestoObserver::class)]
class Puesto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'puestos';

    protected $fillable = [
        'codigo',
        'denominacion',
        'mision',
        'unidad_administrativa_id',
        'grupo_ocupacional_id',
        'plazas',
        'rol_puesto',
        'nivel_complejidad',
        'nivel_jerarquico',
        'regimen_laboral',
        'es_jefe',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_jefe'           => 'boolean',
            'activo'            => 'boolean',
            'nivel_jerarquico'  => 'integer',
            'plazas'            => 'integer',
            'nivel_complejidad' => NivelComplejidadPuesto::class,
            'rol_puesto'        => RolPuesto::class,
        ];
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAdministrativa::class,
            'unidad_administrativa_id'
        );
    }

    public function grupoOcupacional(): BelongsTo
    {
        return $this->belongsTo(GrupoOcupacional::class);
    }

    /**
     * Servidores que actualmente ocupan este puesto
     * a través de contratos vigentes.
     */
    public function contratosVigentes(): HasMany
    {
        return $this->hasMany(
            \App\Models\Expediente\ContratoServidor::class
        )->where('estado', 'vigente');
    }

    /**
     * Plazas ocupadas actualmente.
     */
    public function plazasOcupadas(): int
    {
        return $this->contratosVigentes()->count();
    }

    /**
     * Plazas disponibles (vacantes).
     */
    public function plazasDisponibles(): int
    {
        return max(0, $this->plazas - $this->plazasOcupadas());
    }

    /**
     * Indica si el puesto tiene vacantes disponibles.
     */
    public function tieneVacantes(): bool
    {
        return $this->plazasDisponibles() > 0;
    }

    /**
     * RMU del puesto — viene del grupo ocupacional para LOSEP.
     * Para CT devuelve el SBU referencial.
     */
    public function getRmuAttribute(): ?float
    {
        return $this->grupoOcupacional?->rmu;
    }

    /**
     * Indica si es régimen LOSEP.
     */
    public function esLosep(): bool
    {
        return $this->regimen_laboral === 'losep';
    }
}
