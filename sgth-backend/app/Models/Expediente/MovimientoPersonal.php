<?php

namespace App\Models\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// Sin SoftDeletes por ser inmutable
class MovimientoPersonal extends Model
{
    use LogsActivity;

    protected $table = 'movimientos_personal';

    /**
     * Auditoría: solo campos con relevancia legal, solo cuando cambian
     * (logOnlyDirty) y sin filas vacías (dontSubmitEmptyLogs) — evita que
     * cada save() genere ruido si no tocó ninguno de estos campos. No
     * audita intentos bloqueados por el guard de booted() (esos nunca
     * llegan a persistirse, así que nunca disparan 'updated'); esto
     * registra los cambios de estado efectivamente ocurridos.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo_movimiento',
                'subtipo_movimiento',
                'estado',
                'codigo_registro',
                'fecha_registro',
                'dictamen_presupuestario_ref',
                'notificado_por',
                'fecha_notificacion',
                'corrige_a_id',
                'fecha_suscripcion',
                'firmante_autoridad_nombre',
                'firmante_th_nombre',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => $this->corrige_a_id !== null
                ? 'Corrección de acción de personal registrada'
                : 'Acción de personal creada',
            'updated' => 'Transición de estado de la acción de personal',
            default   => $eventName,
        };
    }

    /**
     * Guarda de inmutabilidad: una vez REGISTRADA (o NOTIFICADA), no se
     * puede modificar tipo_movimiento, fecha_registro ni codigo_registro,
     * y el estado no puede cambiar salvo el único paso legítimo hacia
     * NOTIFICADA. Corre para cualquier update(), no solo el de
     * MovimientoPersonalStateService — una corrección real es un registro
     * nuevo con corrige_a_id, nunca un UPDATE sobre este.
     */
    protected static function booted(): void
    {
        static::updating(function (MovimientoPersonal $movimiento) {
            $estadoOriginal = $movimiento->getOriginal('estado');
            $estadoOriginal = $estadoOriginal instanceof EstadoAccionPersonal
                ? $estadoOriginal
                : EstadoAccionPersonal::tryFrom((string) $estadoOriginal);

            if (!in_array($estadoOriginal, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
                return;
            }

            $inmutables = [
                'tipo_movimiento', 'subtipo_movimiento', 'fecha_registro', 'codigo_registro',
                // Quién firmó y cuándo: sellado al suscribir, es la prueba de
                // auditoría del documento y no puede reescribirse después.
                'fecha_suscripcion',
                'firmante_autoridad_id', 'firmante_autoridad_nombre',
                'firmante_autoridad_cargo', 'firmante_autoridad_cedula',
                'firmante_th_id', 'firmante_th_nombre',
                'firmante_th_cargo', 'firmante_th_cedula',
            ];

            foreach ($inmutables as $campo) {
                if ($movimiento->isDirty($campo)) {
                    throw new ReglaNegocioException(
                        "No se puede modificar '{$campo}' de un evento ya registrado."
                    );
                }
            }

            if ($movimiento->isDirty('estado')) {
                $permitido = $estadoOriginal === EstadoAccionPersonal::REGISTRADA
                    && $movimiento->estado === EstadoAccionPersonal::NOTIFICADA;

                if (!$permitido) {
                    throw new ReglaNegocioException(
                        "No se puede cambiar el estado de un evento en '{$estadoOriginal->etiqueta()}'."
                    );
                }
            }
        });
    }

    protected $fillable = [
        'servidor_id',
        'tipo_movimiento',
        'subtipo_movimiento',
        'requiere_dictamen_medico',
        'categoria',
        'estado',
        'tipo_nombramiento_propuesto',
        'remuneracion_propuesta',
        'fecha_fin_propuesta',
        'numero_contrato',
        'partida_presupuestaria_id',
        'puede_marcar',
        'codigo',
        'codigo_registro',
        'fecha_registro',
        'fecha_suscripcion',
        'firmante_autoridad_id',
        'firmante_autoridad_nombre',
        'firmante_autoridad_cargo',
        'firmante_autoridad_cedula',
        'firmante_th_id',
        'firmante_th_nombre',
        'firmante_th_cargo',
        'firmante_th_cedula',
        'dictamen_presupuestario_ref',
        'corrige_a_id',
        'movimiento_previo_id',
        'cubre_movimiento_id',
        'descripcion',
        'fecha_efectiva',
        'fecha_inicio',
        'fecha_fin',
        'unidad_origen_id',
        'unidad_destino_id',
        'puesto_origen_id',
        'remuneracion_origen',
        'partida_origen_id',
        'puesto_destino_id',
        'resolucion_numero',
        'documento_respaldo',
        'autorizado_por',
        'observacion',
        'lugar_trabajo',
        'caucionado',
        'caucion_numero',
        'caucion_fecha',
    ];

    protected function casts(): array
    {
        return [
            'tipo_movimiento'             => TipoMovimientoPersonal::class,
            'subtipo_movimiento'          => SubtipoMovimientoPersonal::class,
            'requiere_dictamen_medico'    => 'boolean',
            'categoria'                   => CategoriaEventoVinculo::class,
            'estado'                      => EstadoAccionPersonal::class,
            'tipo_nombramiento_propuesto' => TipoNombramiento::class,
            'remuneracion_propuesta'      => 'decimal:2',
            'remuneracion_origen'         => 'decimal:2',
            'fecha_fin_propuesta'         => 'date',
            'puede_marcar'                => 'boolean',
            'fecha_registro'     => 'date',
            'fecha_suscripcion'  => 'date',
            'fecha_efectiva'  => 'date',
            'fecha_inicio'    => 'date',
            'fecha_fin'       => 'date',
            'caucionado'      => 'boolean',
            'caucion_fecha'   => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /**
     * El evento original que este registro rectifica (nunca se edita el
     * original: se crea uno nuevo que lo referencia).
     */
    public function corrigeA(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'corrige_a_id');
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class, 'corrige_a_id');
    }

    /**
     * La acción que habilitó a esta. Hoy solo se usa para encadenar el
     * Ingreso y Vinculación con la Cesación de Funciones previa, que es como
     * Talento Humano opera lo que en otras instituciones sería un ascenso.
     */
    public function movimientoPrevio(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_previo_id');
    }

    /**
     * La ausencia temporal que este ingreso viene a cubrir. Solo lo llevan los
     * reemplazos: un ingreso ordinario lo deja en null.
     */
    public function cubreMovimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'cubre_movimiento_id');
    }

    /** Los ingresos de reemplazo emitidos contra esta ausencia. */
    public function reemplazos(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class, 'cubre_movimiento_id');
    }

    /** Los contratos ya materializados que cubren esta ausencia. */
    public function contratosReemplazo(): HasMany
    {
        return $this->hasMany(ContratoServidor::class, 'cubre_movimiento_id');
    }

    public function movimientosHabilitados(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class, 'movimiento_previo_id');
    }

    public function unidadOrigen(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_origen_id');
    }

    public function unidadDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_destino_id');
    }

    public function puestoOrigen(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_origen_id');
    }

    public function puestoDestino(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_destino_id');
    }

    /**
     * Partida que respaldará el vínculo propuesto. Puede diferir de la del
     * puesto destino: es la que Talento Humano fija en la acción.
     */
    public function partidaPresupuestaria(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestaria::class, 'partida_presupuestaria_id');
    }

    /**
     * La partida que respaldaba el vínculo ANTES de esta acción, congelada al
     * crearla. No se deriva del puesto de origen porque ese puesto puede haber
     * cambiado de partida desde entonces.
     */
    public function partidaOrigen(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestaria::class, 'partida_origen_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    /**
     * Solicitud de ficha de salud ocupacional asociada, cuando la acción
     * requiere dictamen médico. En el flujo de reclutamiento existe desde
     * antes que el movimiento; en el resto se crea al suscribirse.
     */
    public function solicitudCertificacion(): HasOne
    {
        return $this->hasOne(SolicitudCertificacionMedica::class, 'movimiento_personal_id');
    }

    /**
     * El subtipo manda sobre el tipo para todo lo que dependa de la regla de
     * negocio fina. Los tipos planos legados (traslado, traspaso, comision_*,
     * destitucion) no tienen la columna poblada, así que se cae a su
     * equivalente para que las reglas apliquen igual.
     */
    public function subtipoEfectivo(): ?SubtipoMovimientoPersonal
    {
        return $this->subtipo_movimiento
            ?? $this->tipo_movimiento?->subtipoEquivalente();
    }

    /**
     * Acciones que apartan temporalmente al servidor sin tocar su vínculo: las
     * comisiones de servicios y la licencia sin remuneración. El contrato sigue
     * vigente y la plaza ocupada, pero la persona no está — es lo que alimenta
     * la situación mostrada junto al contrato y el listado de ausencias que
     * usa Talento Humano para cubrir el hueco.
     */
    public function esAusenciaTemporal(): bool
    {
        return $this->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION
            || (bool) $this->subtipoEfectivo()?->esAusenciaTemporal();
    }

    /**
     * Etiqueta de la ausencia, para mostrarla junto al contrato.
     */
    public function etiquetaAusencia(): ?string
    {
        if (! $this->esAusenciaTemporal()) {
            return null;
        }

        return $this->subtipoEfectivo()?->etiqueta()
            ?? $this->tipo_movimiento?->etiqueta();
    }

    /**
     * Versión SQL de esAusenciaTemporal(). El predicado en PHP no sirve para
     * listar: obligaría a traer todos los movimientos y filtrarlos en memoria.
     *
     * Contempla las dos formas en que una ausencia queda escrita: con la
     * taxonomía de dos niveles el subtipo la identifica; los tipos planos
     * legados no tienen subtipo y se reconocen por el tipo.
     */
    public function scopeEsAusenciaTemporal(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('subtipo_movimiento', [
                SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
                SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
            ])->orWhere(function (Builder $legado) {
                $legado->whereNull('subtipo_movimiento')->whereIn('tipo_movimiento', [
                    TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
                    TipoMovimientoPersonal::COMISION_SERVICIOS->value,
                    TipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
                ]);
            });
        });
    }

    /**
     * Ausencia vigente a una fecha. El período vive en fecha_inicio/fecha_fin;
     * sin fecha de fin se considera abierta.
     */
    public function scopeAusenciaVigenteEn(Builder $query, string $fecha): Builder
    {
        return $query->whereIn('estado', [
            EstadoAccionPersonal::REGISTRADA->value,
            EstadoAccionPersonal::NOTIFICADA->value,
        ])
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha);
            });
    }
}
