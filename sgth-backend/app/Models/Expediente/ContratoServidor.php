<?php

namespace App\Models\Expediente;

use App\Enums\EstadoContrato;
use App\Enums\OrigenVinculo;
use App\Enums\SupuestoProvisional;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
        // Para qué se dio el provisional (LOSEP 17 b; fase 3.2).
        'supuesto_provisional',
        'numero_contrato',
        'unidad_administrativa_id',
        // Dónde trabaja: uno de los tres lugares de la institución. Vivía solo
        // en la acción; el cambio de lugar del obrero lo mueve (fase 4.3).
        'lugar_trabajo',
        'puesto_id',
        // El puesto que el ascendido dejó, reservado mientras dura su prueba, y
        // el ascenso que lo explica (fase 3.3).
        'puesto_reservado_id',
        'movimiento_ascenso_id',
        'cubre_movimiento_id',
        'movimiento_origen_id',
        'movimiento_cierre_id',
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
            'supuesto_provisional' => SupuestoProvisional::class,
            'estado'            => EstadoContrato::class,
            'origen'            => OrigenVinculo::class,
            'fecha_inicio'      => 'date',
            'fecha_fin'         => 'date',
            'remuneracion'      => 'decimal:2',
            'puede_marcar'      => 'boolean',
        ];
    }

    /** ¿Es un provisional de prueba (b.5): quien ingresó por concurso o quien ascendió? */
    public function esDePrueba(): bool
    {
        return $this->tipo_nombramiento === TipoNombramiento::PROVISIONAL
            && (bool) $this->supuesto_provisional?->esDePrueba();
    }

    /** ¿Es un ascendido en su período de prueba (fase 3.3)? */
    public function esAscensoEnPrueba(): bool
    {
        return $this->tipo_nombramiento === TipoNombramiento::PROVISIONAL
            && $this->supuesto_provisional === SupuestoProvisional::ASCENSO;
    }

    /**
     * Desde cuándo corre la prueba: el inicio del vínculo para quien ingresó, y
     * el día en que rigió el ascenso para quien ascendió, que conserva el
     * vínculo de antes. Null si no es de prueba.
     */
    public function inicioDelPeriodoDePrueba(): ?Carbon
    {
        if (! $this->esDePrueba()) {
            return null;
        }

        return $this->esAscensoEnPrueba()
            ? $this->movimientoAscenso?->fecha_efectiva?->copy()
            : $this->fecha_inicio?->copy();
    }

    /**
     * El último día de la prueba: tres meses quien ingresó y seis quien ascendió
     * (LOSEP 17 b.5). El nombramiento definitivo rige desde el día siguiente.
     * Null si no es de prueba.
     */
    public function finDelPeriodoDePrueba(): ?Carbon
    {
        $inicio = $this->inicioDelPeriodoDePrueba();

        return $inicio
            ? $inicio->addMonthsNoOverflow($this->supuesto_provisional->mesesDePrueba())->subDay()
            : null;
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

    /**
     * La acción de personal que dio origen a este vínculo, y la que lo cerró.
     *
     * Existen para poder deshacer: anular una acción ya registrada tiene que
     * revertir su efecto, y sin estos enlaces no había forma de saber qué
     * contrato había nacido de un ingreso concreto ni cuál había cerrado una
     * cesación concreta. Las dos van en null cuando no aplica — un contrato de
     * carga inicial no nació de ninguna acción, y uno vigente no está cerrado.
     */
    public function movimientoOrigen(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_origen_id');
    }

    public function movimientoCierre(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_cierre_id');
    }

    /** El ascenso que tiene al servidor en prueba, si es el caso (fase 3.3). */
    public function movimientoAscenso(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_ascenso_id');
    }

    /** El puesto que el ascendido dejó y que se le guarda mientras dura la prueba. */
    public function puestoReservado(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_reservado_id');
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', 'vigente')
                     ->where(function ($q) {
                         $q->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', now()->toDateString());
                     });
    }

    /**
     * Los contratos que consumen una plaza del distributivo.
     *
     * Dos exclusiones, y las dos importan:
     *
     *  - Las modalidades que no ocupan plaza (`TipoNombramiento::ocupaPlaza()`):
     *    servicios profesionales y ocasionales se asignan al puesto pero no
     *    descuentan de `plazas`.
     *  - Los reemplazos (`cubre_movimiento_id`): la plaza sigue siendo del
     *    titular en comisión o licencia, cuyo contrato continúa vigente y es el
     *    que la ocupa. Contarlas dos veces bloquearía el puesto justo cuando
     *    Talento Humano necesita cubrir el hueco.
     *
     * Vive aquí y no repetido en cada consulta porque antes lo estaba:
     * `Puesto::plazasOcupadas()` no excluía los reemplazos y
     * `ContratoServidorService::validarVacante()` sí, así que un puesto podía
     * mostrarse sin vacantes y aceptar un contrato nuevo a la vez.
     */
    public function scopeQueOcupanPlaza(Builder $query): Builder
    {
        return $query->where('estado', 'vigente')
                     ->whereNotIn('tipo_nombramiento', TipoNombramiento::valoresSinPlaza())
                     ->whereNull('cubre_movimiento_id');
    }

    /**
     * Los que ocupan una plaza de este puesto: quien está en él y el ascendido
     * en prueba que lo dejó, porque se le reserva hasta que la prueba termine
     * (LOSEP 17 b.5; fase 3.3). Sin la reserva, otro podía ser nombrado en ese
     * puesto y el ascendido que no superara la prueba no tendría a dónde volver.
     */
    public function scopeQueOcupanPlazaDe(Builder $query, int $puestoId): Builder
    {
        return $query->queOcupanPlaza()
                     ->where(fn (Builder $q) => $q->where('puesto_id', $puestoId)
                         ->orWhere('puesto_reservado_id', $puestoId));
    }
}
