<?php

namespace App\Models\Expediente;

use App\Enums\EstadoContrato;
use App\Enums\OrigenVinculo;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\Expediente\ContratoServidorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ContratoServidorObserver::class)]
class ContratoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contratos_servidor';

    protected $appends = ['rau'];

    protected $fillable = [
        'servidor_id',
        'tipo_nombramiento',
        'numero_contrato',
        'unidad_administrativa_id',
        'puesto_id',
        'cubre_movimiento_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo_fin',
        'resolucion_numero',
        'documento_ruta',
        'estado',
        'origen',
        'remuneracion',
        'partida_presupuestaria_id',
        'puede_marcar'
    ];

    protected function casts(): array
    {
        return [
            'tipo_nombramiento' => TipoNombramiento::class,
            'estado'            => EstadoContrato::class,
            'origen'            => OrigenVinculo::class,
            'fecha_inicio'      => 'date',
            'fecha_fin'         => 'date',
            'remuneracion'      => 'decimal:2',
            'puede_marcar'      => 'boolean',
        ];
    }

    /**
     * R.A.U. — Remuneración Anual Unificada, derivada de la R.M.U. del
     * contrato. No se persiste: cambia automáticamente si la remuneración
     * mensual cambia.
     */
    protected function rau(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->remuneracion !== null
                ? round((float) $this->remuneracion * 12, 2)
                : null,
        );
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /**
     * La partida que paga este vínculo. Depende de la modalidad —un ocasional
     * y un permanente en el mismo puesto se imputan a partidas distintas—, por
     * eso vive aquí y no en el puesto.
     */
    public function partidaPresupuestaria(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Estructura\PartidaPresupuestaria::class,
            'partida_presupuestaria_id'
        );
    }

    /**
     * La ausencia temporal que este contrato vino a cubrir. Un contrato con
     * este campo poblado no consume plaza: la plaza sigue siendo del titular
     * ausente, que conserva su vínculo vigente.
     */
    public function cubreMovimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'cubre_movimiento_id');
    }

    public function esReemplazo(): bool
    {
        return $this->cubre_movimiento_id !== null;
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', 'vigente')
                     ->where(function ($q) {
                         $q->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', now()->toDateString());
                     });
    }
}
