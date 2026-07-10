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
        'cargo_id',
        'unidad_administrativa_id',
        'grupo_ocupacional_id',
        'partida_presupuestaria_id',
        'plazas',
        'rol_puesto',
        'nivel_complejidad',
        'regimen_laboral',
        'es_jefe',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_jefe'           => 'boolean',
            'activo'            => 'boolean',
            'plazas'            => 'integer',
            'nivel_complejidad' => NivelComplejidadPuesto::class,
            'rol_puesto'        => RolPuesto::class,
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
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

    public function contratosVigentes(): HasMany
    {
        return $this->hasMany(
            \App\Models\Expediente\ContratoServidor::class
        )->where('estado', 'vigente');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(PuestoActividad::class)
            ->orderBy('orden');
    }

    public function actividadesActivas(): HasMany
    {
        return $this->hasMany(PuestoActividad::class)
            ->where('activo', true)
            ->orderBy('orden');
    }

    public function plazasOcupadas(): int
    {
        return $this->contratosVigentes()->count();
    }

    public function plazasDisponibles(): int
    {
        return max(0, $this->plazas - $this->plazasOcupadas());
    }

    public function tieneVacantes(): bool
    {
        return $this->plazasDisponibles() > 0;
    }

    public function getRmuAttribute(): ?float
    {
        return $this->grupoOcupacional?->rmu
            ? (float) $this->grupoOcupacional->rmu
            : null;
    }

    public function esLosep(): bool
    {
        return $this->regimen_laboral === 'losep';
    }
}
