<?php

namespace App\Services\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Enums\EstadoViatico;
use App\Enums\Permiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoHistorialEstado;
use App\Models\Viatico\ViaticoServidor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cuándo puede hacerse cada cosa con un viático.
 *
 * Quién puede lo decide `ViaticoPolicy`. Aquí va lo que ningún atajo puede
 * saltarse —tampoco el `Gate::before` de admin-ti—:
 *
 * - El grafo de estados. Antes cada acción miraba (o no) el estado a su
 *   manera: la liquidación se confirmaba desde `solicitado`, se rechazaba con el
 *   anticipo ya entregado y los tramos se cambiaban con el viático contabilizado.
 * - Nadie decide sobre un viático en el que viaja, como titular o como
 *   acompañante: no lo aprueba, no le entrega el anticipo, no lo contabiliza ni
 *   autoriza sus vuelos. Tampoco lo corrige como Financiero: para él rige lo
 *   mismo que para cualquier titular.
 * - Cada cambio de estado bloquea la fila y deja su rastro en el historial.
 *
 * Decidido con el usuario:
 * - El titular cambia datos, itinerario y acompañantes solo en `solicitado`;
 *   quien opera, hasta que se liquida.
 * - Se rechaza solo antes de entregar el anticipo.
 * - El servidor cancela su solicitud mientras está `solicitado`.
 */
final class ViaticoEstadoService
{
    /** Desde cada estado, a cuáles se puede pasar. */
    private const TRANSICIONES = [
        'solicitado'            => ['aprobado', 'cancelado', 'rechazado'],
        'aprobado'              => ['con_anticipo', 'en_comision', 'rechazado'],
        'con_anticipo'          => ['en_comision'],
        'en_comision'           => ['pendiente_liquidacion'],
        'pendiente_liquidacion' => ['liquidado'],
        'liquidado'             => ['contabilizado', 'pendiente_liquidacion'],
        'contabilizado'         => [],
        'cancelado'             => [],
        'rechazado'             => [],
    ];

    /** Hasta dónde corrige quien opera los viáticos. */
    private const EDITABLES_POR_QUIEN_OPERA = [
        EstadoViatico::SOLICITADO,
        EstadoViatico::APROBADO,
        EstadoViatico::CON_ANTICIPO,
        EstadoViatico::EN_COMISION,
        EstadoViatico::PENDIENTE_LIQUIDACION,
    ];

    public function __construct(
        private readonly JefeFinancieroService $jefeFinanciero,
        private readonly ViaticoServiceInterface $viaticos,
        private readonly CalculoViaticoService $calculo,
        private readonly FirmanteViaticoService $firmantes,
    ) {}

    // ── Transiciones ─────────────────────────────────────────────────

    /** El registro inicial: el viático nace `solicitado`. */
    public function registrarCreacion(Viatico $viatico, int $userId): void
    {
        ViaticoHistorialEstado::create([
            'viatico_id'   => $viatico->id,
            'estado_nuevo' => EstadoViatico::SOLICITADO->value,
            'usuario_id'   => $userId,
        ]);
    }

    /**
     * @param array{coeficiente_exterior?: float|string|null, pais_destino?: string|null} $datos
     */
    public function aprobar(int $viaticoId, User $user, array $datos = []): Viatico
    {
        return $this->transicionar($viaticoId, $user, EstadoViatico::APROBADO, null, function (Viatico $viatico) use ($user, $datos) {
            $this->noSobreElPropio($viatico, $user, 'aprobar');

            if (! $viatico->tramos()->exists()) {
                throw new ReglaNegocioException(
                    'El viático no tiene itinerario: registre al menos un tramo antes de aprobarlo.'
                );
            }

            if ($viatico->tieneAutorizacionesPendientes()) {
                throw new ReglaNegocioException(
                    'El viático tiene autorizaciones de vuelo pendientes: resuélvalas antes de aprobarlo.'
                );
            }

            if ($this->viaticos->verificarBloqueo($viatico->servidor_id)) {
                throw new ReglaNegocioException(
                    'El servidor tiene liquidaciones de viático fuera del plazo de '
                        . ViaticoService::DIAS_HABILES_PARA_LIQUIDAR . ' días hábiles.'
                );
            }

            $this->aplicarTarifaExterior($viatico, $datos);

            // La solicitud se emite al aprobarla: sus firmas quedan selladas
            // con quien ejerce cada cargo hoy.
            $this->firmantes->sellar($viatico, FirmanteViaticoService::SOLICITUD);
        }, 'Solo se aprueba un viático solicitado.');
    }

    public function rechazar(int $viaticoId, User $user, string $motivo): Viatico
    {
        return $this->transicionar($viaticoId, $user, EstadoViatico::RECHAZADO, $motivo, function (Viatico $viatico) use ($motivo) {
            $viatico->motivo_rechazo = $motivo;
        }, 'Solo se rechaza un viático solicitado o aprobado, antes de entregar el anticipo.');
    }

    public function cancelar(int $viaticoId, User $user): Viatico
    {
        return $this->transicionar(
            $viaticoId, $user, EstadoViatico::CANCELADO, null, null,
            'Solo se cancela una solicitud que aún no se ha aprobado.'
        );
    }

    /**
     * @param array{numero_resolucion?: string, partida_presupuestaria?: string} $datos
     */
    public function entregarAnticipo(int $viaticoId, User $user, array $datos = []): Viatico
    {
        return $this->transicionar($viaticoId, $user, EstadoViatico::CON_ANTICIPO, null, function (Viatico $viatico) use ($user, $datos) {
            $this->noSobreElPropio($viatico, $user, 'entregarle el anticipo a');

            $this->anotarRespaldo($viatico, $datos);

            if ($this->valor($viatico->modalidad_anticipo) === 'sin_anticipo') {
                throw new ReglaNegocioException(
                    'El viático se solicitó sin anticipo: pase directamente a la comisión.'
                );
            }

            $viatico->monto_anticipo = round(
                (float) $viatico->monto_calculado * CalculoViaticoService::PORCENTAJE_JUSTIFICABLE,
                2
            );
        }, 'Solo se entrega el anticipo de un viático aprobado.');
    }

    public function marcarEnComision(int $viaticoId, User $user): Viatico
    {
        return $this->transicionar(
            $viaticoId, $user, EstadoViatico::EN_COMISION, null, null,
            'El viático debe estar aprobado o con anticipo para marcarse en comisión.'
        );
    }

    public function marcarPendienteLiquidacion(int $viaticoId, User $user): Viatico
    {
        return $this->transicionar(
            $viaticoId, $user, EstadoViatico::PENDIENTE_LIQUIDACION, null, null,
            'El viático debe estar en comisión para marcarse pendiente de liquidación.'
        );
    }

    /** El servidor presenta la liquidación; la deja lista para revisar. */
    public function confirmarLiquidacion(int $viaticoId, User $user): Viatico
    {
        return $this->transicionar($viaticoId, $user, EstadoViatico::LIQUIDADO, null, function (Viatico $viatico) use ($user) {
            $liquidacion = LiquidacionViatico::where('viatico_id', $viatico->id)
                ->withCount(['actividades', 'detallesFactura'])
                ->first();

            if (! $liquidacion || $liquidacion->actividades_count === 0) {
                throw new ReglaNegocioException('Debe registrar al menos una actividad.');
            }

            if ($liquidacion->detalles_factura_count === 0) {
                throw new ReglaNegocioException('Debe registrar al menos un comprobante.');
            }

            $liquidacion->update([
                'fecha_liquidacion' => now()->toDateString(),
                'updated_by'        => $user->id,
            ]);

            // La cuenta se cierra con lo que hay al presentarla, no con lo que
            // quedó guardado la última vez que se tocaron los comprobantes.
            $this->calculo->guardarEn($liquidacion, $viatico);

            $this->firmantes->sellar($viatico, FirmanteViaticoService::INFORME);
        }, 'La liquidación solo se presenta con el viático pendiente de liquidación.');
    }

    public function devolverCorreccion(int $viaticoId, User $user, string $motivo): Viatico
    {
        return $this->transicionar(
            $viaticoId, $user, EstadoViatico::PENDIENTE_LIQUIDACION, $motivo, null,
            'Solo se devuelve a corrección un viático liquidado.',
            desde: [EstadoViatico::LIQUIDADO],
        );
    }

    /**
     * @param array{numero_resolucion?: string, partida_presupuestaria?: string} $datos
     */
    public function contabilizar(int $viaticoId, User $user, array $datos = []): LiquidacionViatico
    {
        $viatico = $this->transicionar($viaticoId, $user, EstadoViatico::CONTABILIZADO, null, function (Viatico $viatico) use ($user, $datos) {
            $this->noSobreElPropio($viatico, $user, 'contabilizar');

            $this->anotarRespaldo($viatico, $datos);

            if (! $viatico->numero_resolucion || ! $viatico->partida_presupuestaria) {
                throw new ReglaNegocioException(
                    'El viático no tiene número de resolución ni partida presupuestaria: '
                        .'asígnelos antes de contabilizarlo.'
                );
            }

            $liquidacion = LiquidacionViatico::where('viatico_id', $viatico->id)->first();

            if (! $liquidacion) {
                throw new ReglaNegocioException('El viático no tiene liquidación registrada.');
            }

            // Todos los comprobantes aceptados (decidido con el usuario). Se
            // resuelve aquí y no en el constructor: el servicio de comprobantes
            // ya depende de este.
            app(ComprobantesViaticoService::class)->asegurarTodoAceptado($liquidacion);

            $this->firmantes->sellar($viatico, FirmanteViaticoService::COMPROBANTE);

            $jefe = $this->jefeFinanciero->obtenerJefeFinanciero();

            $liquidacion->update([
                'jefe_financiero_id'    => $jefe['user_id'],
                'cargo_jefe_financiero' => $jefe['cargo'],
                'contabilizado_por'     => $user->id,
                'fecha_contabilizacion' => now()->toDateString(),
            ]);
        }, 'Solo se contabiliza un viático liquidado.');

        return $viatico->liquidacion()->firstOrFail();
    }

    /** Aprobar o rechazar una autorización de vuelo pendiente. */
    public function decidirVuelo(
        int $autorizacionId,
        User $user,
        string $decision,
        ?string $observacion
    ): AutorizacionVuelo {
        return DB::transaction(function () use ($autorizacionId, $user, $decision, $observacion) {
            $autorizacion = AutorizacionVuelo::lockForUpdate()->findOrFail($autorizacionId);

            if ($autorizacion->estado !== 'pendiente') {
                throw new ReglaNegocioException("La autorización de vuelo ya fue {$autorizacion->estado}.");
            }

            $this->noSobreElPropio(
                $autorizacion->viatico()->firstOrFail(), $user, 'autorizar los vuelos de'
            );

            $autorizacion->update([
                'estado'                => $decision,
                'aprobado_por'          => $user->id,
                'observacion_aprobador' => $observacion,
                'aprobado_en'           => now(),
            ]);

            return $autorizacion;
        });
    }

    // ── Tarea programada ─────────────────────────────────────────────

    /**
     * Pone el estado al día con las fechas del viaje.
     *
     * «En comisión» y «pendiente de liquidación» dependían de que Financiero
     * pulsara un botón. Si no lo hacía, el viático seguía «aprobado» después de
     * volver, y el plazo de 4 días hábiles para liquidar —que solo cuenta con
     * el viático pendiente de liquidación— nunca empezaba: el bloqueo por
     * liquidaciones vencidas no se aplicaba a nadie.
     *
     * Decidido con el usuario:
     * - Llegada la salida, lo aprobado y lo que tiene anticipo pasa a «en
     *   comisión». Si el anticipo no se entregó, el viaje igual empezó: queda
     *   como un viático sin anticipo y lo que corresponda se paga al liquidar.
     * - Una solicitud sin aprobar no se toca: Financiero todavía puede
     *   aprobarla con retraso o rechazarla.
     * - Llegado el regreso, lo que está en comisión pasa a pendiente de
     *   liquidación.
     *
     * Un viaje que empezó y terminó desde la última corrida da los dos pasos.
     * Los botones manuales siguen: sirven para adelantarse a la tarea.
     *
     * @return array{en_comision: int, pendiente_liquidacion: int}
     */
    public function avanzarPorFechas(?CarbonInterface $ahora = null): array
    {
        $ahora ??= Carbon::now();

        $salieron = Viatico::whereIn('estado', [EstadoViatico::APROBADO, EstadoViatico::CON_ANTICIPO])
            ->where('datetime_salida', '<=', $ahora)
            ->orderBy('id')
            ->pluck('id');

        $enComision = $salieron->filter(fn (int $id) => $this->avanzar(
            $id, EstadoViatico::EN_COMISION,
            function (Viatico $viatico) use ($ahora) {
                if ($viatico->datetime_salida->gt($ahora)) {
                    return false;
                }

                if ($viatico->estado === EstadoViatico::APROBADO
                    && $this->valor($viatico->modalidad_anticipo) !== 'sin_anticipo'
                ) {
                    $viatico->modalidad_anticipo = 'sin_anticipo';

                    return 'Comenzó la comisión sin que se entregara el anticipo; '
                        . 'se liquida como un viático sin anticipo.';
                }

                return 'Comenzó la comisión.';
            },
        ))->count();

        $volvieron = Viatico::where('estado', EstadoViatico::EN_COMISION)
            ->where('datetime_llegada', '<=', $ahora)
            ->orderBy('id')
            ->pluck('id');

        $pendientes = $volvieron->filter(fn (int $id) => $this->avanzar(
            $id, EstadoViatico::PENDIENTE_LIQUIDACION,
            fn (Viatico $viatico) => $viatico->datetime_llegada->gt($ahora)
                ? false
                : 'Terminó la comisión; corre el plazo para liquidar.',
        ))->count();

        return ['en_comision' => $enComision, 'pendiente_liquidacion' => $pendientes];
    }

    /**
     * Un paso de la tarea programada. La condición se vuelve a mirar con la
     * fila bloqueada: entre la consulta y el paso, alguien pudo cambiar las
     * fechas o el estado. Si ya no aplica, se deja como está.
     *
     * @param callable(Viatico): (string|false) $condicion Devuelve el motivo
     *        del paso, o `false` si ya no corresponde darlo.
     */
    private function avanzar(int $viaticoId, EstadoViatico $destino, callable $condicion): bool
    {
        try {
            $this->transicionar($viaticoId, null, $destino, null, function (Viatico $viatico) use ($condicion) {
                $motivo = $condicion($viatico);

                if ($motivo === false) {
                    throw new ReglaNegocioException('Ya no corresponde.');
                }

                return $motivo;
            });

            return true;
        } catch (ReglaNegocioException) {
            return false;
        }
    }

    // ── Reglas para lo que no es una transición ──────────────────────

    /**
     * Datos, itinerario y acompañantes. El titular, solo mientras está
     * `solicitado`; quien opera, hasta que se liquida, salvo en un viático en
     * el que viaja.
     */
    public function asegurarEditable(Viatico $viatico, User $user): void
    {
        $estado = $viatico->estado;

        if ($this->opera($user) && ! $this->viajaEn($viatico, $user)) {
            if (! in_array($estado, self::EDITABLES_POR_QUIEN_OPERA, true)) {
                throw new ReglaNegocioException(
                    "Un viático {$estado->value} ya no se modifica."
                );
            }

            return;
        }

        if ($estado !== EstadoViatico::SOLICITADO) {
            throw new ReglaNegocioException(
                'La solicitud solo se modifica antes de ser aprobada. Pida a Financiero la corrección.'
            );
        }
    }

    /** Fijar el monto a mano: nunca en un viático en el que se viaja. */
    public function asegurarMontoAjeno(Viatico $viatico, User $user): void
    {
        $this->noSobreElPropio($viatico, $user, 'fijar el monto de');
    }

    /** Actividades y comprobantes se registran con la liquidación abierta. */
    public function asegurarLiquidacionAbierta(Viatico $viatico): void
    {
        if ($viatico->estado !== EstadoViatico::PENDIENTE_LIQUIDACION) {
            throw new ReglaNegocioException(
                'La liquidación solo se modifica con el viático pendiente de liquidación.'
            );
        }
    }

    public function viajaEn(Viatico $viatico, User $user): bool
    {
        if ($user->servidor_id === null) {
            return false;
        }

        return (int) $viatico->servidor_id === (int) $user->servidor_id
            || ViaticoServidor::where('viatico_id', $viatico->id)
                ->where('servidor_id', $user->servidor_id)
                ->exists();
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Bloquea el viático, comprueba que el paso esté en el grafo, aplica lo
     * propio de la acción, cambia el estado y lo anota en el historial.
     *
     * Sin usuario, el paso lo dio la tarea programada: queda sin autor.
     *
     * @param list<EstadoViatico>|null $desde Restringe aún más el origen,
     *        cuando el destino se alcanza desde varios estados.
     */
    private function transicionar(
        int $viaticoId,
        ?User $user,
        EstadoViatico $destino,
        ?string $motivo,
        ?callable $aplicar,
        ?string $mensaje = null,
        ?array $desde = null,
    ): Viatico {
        return DB::transaction(function () use ($viaticoId, $user, $destino, $motivo, $aplicar, $mensaje, $desde) {
            $viatico = Viatico::lockForUpdate()->findOrFail($viaticoId);
            $origen  = $viatico->estado;

            $permitido = in_array($destino->value, self::TRANSICIONES[$origen->value] ?? [], true)
                && ($desde === null || in_array($origen, $desde, true));

            if (! $permitido) {
                throw new ReglaNegocioException(
                    $mensaje ?? "Un viático {$origen->value} no puede pasar a {$destino->value}."
                );
            }

            // Lo propio de la acción. Si devuelve texto, ese es el motivo del
            // paso: lo usa la tarea programada, que lo decide con la fila ya
            // bloqueada.
            if ($aplicar) {
                $resultado = $aplicar($viatico);
                $motivo = is_string($resultado) ? $resultado : $motivo;
            }

            $viatico->estado     = $destino;
            $viatico->updated_by = $user?->id;
            $viatico->save();

            ViaticoHistorialEstado::create([
                'viatico_id'      => $viatico->id,
                'estado_anterior' => $origen->value,
                'estado_nuevo'    => $destino->value,
                'motivo'          => $motivo,
                'usuario_id'      => $user?->id,
            ]);

            return $viatico->fresh();
        });
    }

    /**
     * Anota la resolución y la partida que asigna Financiero. Se guardan con el
     * cambio de estado, dentro de la misma transacción.
     *
     * @param array{numero_resolucion?: string, partida_presupuestaria?: string} $datos
     */
    private function anotarRespaldo(Viatico $viatico, array $datos): void
    {
        foreach (['numero_resolucion', 'partida_presupuestaria'] as $campo) {
            if (! empty($datos[$campo])) {
                $viatico->{$campo} = trim($datos[$campo]);
            }
        }
    }

    private function noSobreElPropio(Viatico $viatico, User $user, string $accion): void
    {
        if ($this->viajaEn($viatico, $user)) {
            throw new ReglaNegocioException(
                "No puede {$accion} un viático en el que viaja: debe hacerlo otra persona de Financiero."
            );
        }
    }

    private function opera(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_VIATICOS->value);
    }

    /**
     * Para el exterior el monto se fija al aprobar: la tarifa base del
     * catálogo, por el coeficiente del país que ingresa Financiero, por las
     * noches de la comisión.
     */
    private function aplicarTarifaExterior(Viatico $viatico, array $datos): void
    {
        if ($this->valor($viatico->zona) !== 'exterior'
            || ! isset($datos['coeficiente_exterior'])
            || (float) $datos['coeficiente_exterior'] <= 0
        ) {
            return;
        }

        $coeficiente = (float) $datos['coeficiente_exterior'];

        $viatico->monto_calculado = $this->calculo->derecho(
            $viatico->servidor,
            'exterior',
            (int) $viatico->noches,
            $coeficiente
        );
        $viatico->coeficiente_exterior = $coeficiente;
        $viatico->pais_destino         = $datos['pais_destino'] ?? $viatico->pais_destino;
    }

    private function valor(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }
}
