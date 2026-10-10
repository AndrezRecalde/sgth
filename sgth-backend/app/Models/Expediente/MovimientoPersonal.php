<?php

namespace App\Models\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\ClaseAccionPersonal;
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
                'clase',
                'estado',
                'codigo_registro',
                'fecha_registro',
                // Cuándo surtió efecto: con fecha futura, no al registrarse
                // sino el día en que rige (fase 1.6).
                'efecto_aplicado_en',
                'dictamen_presupuestario_ref',
                'notificado_por',
                'fecha_notificacion',
                // Por qué se anuló: es la justificación de un acto sobre otro
                // acto, así que va al registro de auditoría con el resto.
                'motivo_anulacion',
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
            'created' => 'Acción de personal creada',
            'updated' => 'Transición de estado de la acción de personal',
            default   => $eventName,
        };
    }

    /**
     * Guarda de inmutabilidad: una vez REGISTRADA (o NOTIFICADA), no se
     * puede modificar tipo_movimiento, fecha_registro ni codigo_registro, y el
     * estado solo puede avanzar a NOTIFICADA o quedar en ANULADA. Corre para
     * cualquier update(), no solo el de MovimientoPersonalStateService: un acto
     * registrado con un error se anula y se emite uno nuevo, nunca se reescribe
     * este.
     */
    protected static function booted(): void
    {
        /*
        | La clase se deriva del tipo y el subtipo cuando nadie la fija. La fija
        | quien la conoce mejor que el par tipo/subtipo: el formulario, que
        | crea por clase, y `SubrogacionService`, que es el único que sabe si
        | una subrogación es en realidad un encargo.
        |
        | Así no hace falta tocar cada servicio que crea acciones —Disciplinario,
        | visto bueno, reclutamiento, contratos vencidos—: siguen creando por
        | tipo y la clase les llega igual.
        */
        static::saving(function (MovimientoPersonal $movimiento) {
            $fijadaAMano = $movimiento->isDirty('clase') && $movimiento->clase !== null;

            // Un modelo leído con `select` parcial no trae la columna: derivarla
            // ahí la marcaría como cambiada, y en una acción registrada la
            // guarda de inmutabilidad lo rechazaría sin que nadie la tocara.
            $sinLaColumna = $movimiento->exists
                && ! array_key_exists('clase', $movimiento->getAttributes());

            if ($fijadaAMano || $sinLaColumna) {
                return;
            }

            if ($movimiento->clase === null
                || $movimiento->isDirty(['tipo_movimiento', 'subtipo_movimiento'])
            ) {
                // Una subrogación ya clasificada no se reclasifica: si es un
                // encargo, desde el tipo no hay forma de saberlo.
                if ($movimiento->clase !== null
                    && $movimiento->tipo_movimiento === TipoMovimientoPersonal::SUBROGACION
                ) {
                    return;
                }

                $movimiento->clase = ClaseAccionPersonal::desde(
                    $movimiento->tipo_movimiento,
                    $movimiento->subtipoEfectivo()
                );
            }
        });

        static::updating(function (MovimientoPersonal $movimiento) {
            $estadoOriginal = $movimiento->getOriginal('estado');
            $estadoOriginal = $estadoOriginal instanceof EstadoAccionPersonal
                ? $estadoOriginal
                : EstadoAccionPersonal::tryFrom((string) $estadoOriginal);

            // Una acción anulada es el final del camino: no cambia nada más,
            // ni el estado.
            if ($estadoOriginal === EstadoAccionPersonal::ANULADA) {
                throw new ReglaNegocioException(
                    'Una acción de personal anulada no se modifica. Si hace falta dejar '
                        .'constancia de algo, anótelo.'
                );
            }

            if (!in_array($estadoOriginal, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
                return;
            }

            /*
            | Desde que está registrada, de la acción solo cambia el estado, con
            | los datos de ese paso. Todo lo demás —la explicación, las fechas,
            | los puestos, la remuneración, el dictamen— es el acto emitido, y no
            | se reescribe (diseño, 8.1). Lo que pase después se anota aparte,
            | en `anotaciones_accion_personal`.
            |
            | Hasta la fase 1.4 esto era una lista de campos prohibidos —tipo,
            | clase, número y firmantes— y todo lo que no estaba en ella se podía
            | cambiar: el visto bueno impugnado reescribía la explicación de una
            | cesación ya registrada. Una lista de lo permitido no deja huecos
            | cuando se añade una columna.
            */
            $permitidos = [
                'estado', 'updated_at',
                // El efecto de una acción que rige más tarde se aplica el día
                // en que rige, una sola vez: de vacío a fecha, nunca al revés.
                ...($movimiento->getOriginal('efecto_aplicado_en') === null ? ['efecto_aplicado_en'] : []),
                ...match ($movimiento->estado) {
                    EstadoAccionPersonal::NOTIFICADA => ['notificado_por', 'fecha_notificacion'],
                    EstadoAccionPersonal::ANULADA    => ['motivo_anulacion'],
                    default                          => [],
                },
            ];

            $cambiados = array_values(array_diff(array_keys($movimiento->getDirty()), $permitidos));

            if ($cambiados !== []) {
                throw new ReglaNegocioException(
                    'Una acción de personal registrada no se modifica (campos: '
                        .implode(', ', $cambiados).'). Si hace falta dejar constancia de '
                        .'algo, anótelo; si el acto tiene un error, anúlelo y emita uno nuevo.'
                );
            }

            /*
            | Los dos únicos pasos legítimos desde aquí: seguir adelante hacia
            | NOTIFICADA, o quedar sin efecto.
            |
            | ANULADA se añadió el 2026-09-29. Antes la única salida era
            | notificar, y un acto registrado con un error no tenía ninguna:
            | ni se editaba —lo de arriba— ni se anulaba, así que el único
            | recurso era `corregir()`, que emitía un segundo documento con
            | otro correlativo y dejaba el primero vigente. TH: lo correcto es
            | anular y emitir uno nuevo.
            |
            | Esto solo levanta el candado del modelo. Qué se puede anular y
            | cómo se deshace su efecto sobre el vínculo lo decide
            | MovimientoPersonalStateService, que es quien conoce el grafo.
            */
            if ($movimiento->isDirty('estado')) {
                $destinosPermitidos = [
                    EstadoAccionPersonal::NOTIFICADA,
                    EstadoAccionPersonal::ANULADA,
                ];

                if (!in_array($movimiento->estado, $destinosPermitidos, true)) {
                    throw new ReglaNegocioException(
                        "No se puede cambiar el estado de un evento en '{$estadoOriginal->etiqueta()}'"
                            ." a '{$movimiento->estado->etiqueta()}'."
                    );
                }
            }
        });
    }

    protected $fillable = [
        'servidor_id',
        'tipo_movimiento',
        'subtipo_movimiento',
        'clase',
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
        'efecto_aplicado_en',
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
        'movimiento_previo_id',
        'cubre_movimiento_id',
        'descripcion',
        'fecha_efectiva',
        'fecha_inicio',
        'fecha_fin',
        'unidad_origen_id',
        'unidad_destino_id',
        // A qué entidad del Estado va, en comisiones e intercambio (fase 2.3).
        'institucion_destino',
        'para_estudios_o_eventos',
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
        // No entra en los campos copiables de corregir(): una corrección nace en
        // borrador y no arrastra por qué se anuló la anterior.
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo_movimiento'             => TipoMovimientoPersonal::class,
            'subtipo_movimiento'          => SubtipoMovimientoPersonal::class,
            'clase'                       => ClaseAccionPersonal::class,
            'requiere_dictamen_medico'    => 'boolean',
            'categoria'                   => CategoriaEventoVinculo::class,
            'estado'                      => EstadoAccionPersonal::class,
            'tipo_nombramiento_propuesto' => TipoNombramiento::class,
            'remuneracion_propuesta'      => 'decimal:2',
            'remuneracion_origen'         => 'decimal:2',
            'fecha_fin_propuesta'         => 'date',
            'puede_marcar'                => 'boolean',
            'para_estudios_o_eventos'     => 'boolean',
            'fecha_registro'     => 'date',
            'efecto_aplicado_en' => 'datetime',
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
     * La subrogación o encargo que esta acción respalda, si es de ese tipo.
     *
     * La clave vive del lado de subrogaciones porque la subrogación existe
     * primero y la acción se crea para respaldarla. Desde aquí hace falta para
     * el documento impreso: ambos comparten tipo_movimiento 'subrogacion' y
     * solo esta fila dice cuál de los dos es.
     */
    public function subrogacion(): HasOne
    {
        return $this->hasOne(Subrogacion::class, 'movimiento_personal_id');
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

    /** Lo que se le anotó después de emitida, en el orden en que pasó. */
    public function anotaciones(): HasMany
    {
        return $this->hasMany(AnotacionAccionPersonal::class)->orderBy('created_at')->orderBy('id');
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
     * ¿Esta acción mueve al servidor a otro puesto dentro del mismo vínculo?
     *
     * La pregunta se responde por dos vías porque el modelo tiene dos niveles:
     * el traspaso lo decide su subtipo, y la prestación de servicios su tipo,
     * porque no tiene subtipo. Antes solo se miraba el subtipo, así que la
     * prestación de servicios se registraba sin reubicar a nadie.
     *
     * Es el único sitio donde se decide: lo consultan tanto la exigencia de
     * puesto de destino como la reubicación en sí. La pantalla ya no lo
     * copia: le llega en `propone_situacion` del recurso y, al crear, en
     * `pide_situacion_propuesta` del catálogo.
     */
    public function reubicaAlServidor(): bool
    {
        return $this->tipo_movimiento?->reubicaAlServidor()
            || (bool) $this->subtipoEfectivo()?->modificaPuesto();
    }

    /**
     * ¿Registrar esta acción cambia algo en el ContratoServidor?
     *
     * Las tres ramas de `MovimientoPersonalStateService::aplicarRegistro()`,
     * en una sola pregunta: crea el vínculo, reubica dentro de él, o lo cierra.
     * El resto de acciones —comisiones, licencias, sanciones, cambios de
     * denominación, incrementos— se registran sin tocarlo.
     *
     * Existe para que anular pregunte exactamente lo mismo que registrar: la
     * reversión tiene las mismas tres ramas, y si un día se añade un tipo que
     * toque el vínculo y solo se actualiza una de las dos listas, su anulación
     * dejaría el contrato afirmando algo que ningún acto respalda.
     */
    public function tocaElVinculo(): bool
    {
        return (bool) $this->tipo_movimiento?->creaVinculo()
            || $this->reubicaAlServidor()
            || (bool) $this->subtipoEfectivo()?->cierraVinculo();
    }

    /**
     * La causal de la acción, si su clase la tiene. Hoy solo la cesación, y es
     * su subtipo: renuncia, destitución, jubilación…
     */
    public function causal(): ?SubtipoMovimientoPersonal
    {
        return $this->clase?->requiereCausal() ? $this->subtipoEfectivo() : null;
    }

    /**
     * Cómo se llama la acción en pantalla y en el documento: el nombre de su
     * clase, y el de su tipo en el raro caso de que no tenga una.
     */
    public function etiqueta(): string
    {
        return $this->clase?->etiqueta()
            ?? $this->tipo_movimiento?->etiqueta()
            ?? '—';
    }

    /**
     * ¿Esta acción deja al servidor en una situación nueva que el documento
     * deba mostrar frente a la actual?
     *
     * El ingreso —que crea el vínculo—, lo que reubica dentro de él, y la
     * subrogación o el encargo: aunque el vínculo original se conserva, el
     * servidor pasa a ejercer otro puesto y a cobrar por él. Una cesación, una
     * comisión, una licencia o una sanción no proponen nada.
     */
    public function proponeSituacion(): bool
    {
        return $this->tipo_movimiento === TipoMovimientoPersonal::INGRESO
            || $this->tipo_movimiento === TipoMovimientoPersonal::SUBROGACION
            || $this->reubicaAlServidor();
    }

    /**
     * ¿Se corrige con el formulario de «Nueva acción de personal»?
     *
     * Solo las acciones con una clase que ese formulario crea: la subrogación y
     * el encargo se corrigen cancelándolos en su pantalla.
     */
    public function editableEnFormulario(): bool
    {
        return (bool) $this->clase?->seCreaDesdeElFormulario();
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

        // La licencia dice qué es y por qué: «asuntos particulares» solo, junto
        // al contrato, no decía que el servidor estaba de licencia.
        if ($this->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION) {
            $causal = $this->subtipoEfectivo()?->etiqueta();

            return $this->tipo_movimiento->etiqueta().($causal ? " ({$causal})" : '');
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
            ])
            // La licencia es ausencia con causal o sin ella: desde la fase 2.2
            // lleva subtipo, y las anteriores no.
            ->orWhere('tipo_movimiento', TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value)
            ->orWhere(function (Builder $legado) {
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

    /**
     * Lo que el titular puede ver de sus propias acciones (diseño, 6.3): los
     * actos registrados o notificados, y los anulados que llegaron a tener
     * número —esos circularon y su anulación también le concierne—. Un
     * borrador anulado nunca fue nada.
     */
    public function scopeVisibleParaElTitular(Builder $query): Builder
    {
        return $query->whereIn('estado', array_map(
            fn (EstadoAccionPersonal $e) => $e->value,
            EstadoAccionPersonal::visiblesParaElTitular(),
        ))->where(function (Builder $q) {
            $q->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
                ->orWhereNotNull('codigo_registro');
        });
    }

    /**
     * Registrada, pero su efecto todavía no se aplicó porque rige más tarde
     * (diseño, 6.1 y 6.2; fase 1.6). No es un estado: es una situación que se
     * deduce, y el comando diario la cierra el día en que rige.
     */
    public function scopePendientesDeVigencia(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            EstadoAccionPersonal::REGISTRADA->value,
            EstadoAccionPersonal::NOTIFICADA->value,
        ])
            ->whereNotNull('codigo_registro')
            ->whereNull('efecto_aplicado_en');
    }

    public function pendienteDeVigencia(): bool
    {
        return in_array($this->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)
            && filled($this->codigo_registro)
            && $this->efecto_aplicado_en === null;
    }

    /** ¿Ya rige en esa fecha? */
    public function rigeEn(string $fecha): bool
    {
        return $this->fecha_efectiva !== null && $this->fecha_efectiva->toDateString() <= $fecha;
    }

    /** Las cesaciones: renuncia, destitución, jubilación… */
    public function cierraElVinculo(): bool
    {
        return (bool) $this->subtipoEfectivo()?->cierraVinculo();
    }

    public function esVisibleParaElTitular(): bool
    {
        if (! in_array($this->estado, EstadoAccionPersonal::visiblesParaElTitular(), true)) {
            return false;
        }

        return $this->estado !== EstadoAccionPersonal::ANULADA || filled($this->codigo_registro);
    }

    /** ¿La persona que pregunta es aquella sobre quien versa la acción? */
    public function esSobre(?User $usuario): bool
    {
        return $usuario?->servidor_id !== null
            && (int) $usuario->servidor_id === (int) $this->servidor_id;
    }
}
