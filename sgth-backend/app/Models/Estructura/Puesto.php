<?php
namespace App\Models\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Observers\PuestoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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

    /** ¿Es del nivel jerárquico superior? Lo dice su grupo ocupacional. */
    public function esNivelJerarquicoSuperior(): bool
    {
        return (bool) $this->grupoOcupacional?->esNivelJerarquicoSuperior();
    }

    public function partidaPresupuestaria(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestaria::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(\App\Models\Expediente\ContratoServidor::class);
    }

    /**
     * Si está ocupado y si tiene historia, calculados en la misma consulta
     * (fase 7.1a): el listado los necesita para cada fila, y uno por fila serían
     * dos consultas más cada vez. Lo lee `ProteccionDePuestoOcupado`, que
     * consulta así mismo cuando el puesto llega sin ellos.
     *
     * - `ocupado`: un contrato vigente que lo ocupa o lo reserva —el del
     *   ascendido en prueba—, o una acción registrada que lo tiene de destino y
     *   todavía no rige.
     * - `con_historia`: cualquier contrato, también los cerrados o anulados,
     *   cualquier acción que lo nombre de origen o de destino, o una subrogación.
     */
    public function scopeConOcupacion(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('puestos.*');
        }

        return $query->selectRaw(<<<'SQL'
            (
                exists (
                    select 1 from contratos_servidor c
                    where c.deleted_at is null and c.estado = 'vigente'
                      and (c.puesto_id = puestos.id or c.puesto_reservado_id = puestos.id)
                )
                or exists (
                    select 1 from movimientos_personal m
                    where m.puesto_destino_id = puestos.id
                      and m.estado in ('registrada', 'notificada')
                      and m.codigo_registro is not null
                      and m.efecto_aplicado_en is null
                )
            ) as ocupado,
            (
                exists (
                    select 1 from contratos_servidor c
                    where c.puesto_id = puestos.id or c.puesto_reservado_id = puestos.id
                )
                or exists (
                    select 1 from movimientos_personal m
                    where m.puesto_origen_id = puestos.id or m.puesto_destino_id = puestos.id
                )
                or exists (select 1 from subrogaciones s where s.puesto_subrogado_id = puestos.id)
            ) as con_historia
            SQL);
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

    public function equiposProteccionRequeridos(): HasMany
    {
        return $this->hasMany(\App\Models\Sso\PuestoEpp::class);
    }

    public function plazasOcupadas(): int
    {
        // Quién ocupa plaza lo define `ContratoServidor::scopeQueOcupanPlaza()`,
        // el mismo criterio que usa la validación de vacante al contratar. El
        // ascendido en prueba la sigue ocupando aquí: se le reserva (fase 3.3).
        return \App\Models\Expediente\ContratoServidor::queOcupanPlazaDe($this->id)->count();
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
