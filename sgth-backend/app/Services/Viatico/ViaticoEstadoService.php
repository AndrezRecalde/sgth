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
                    'El servidor tiene liquidaciones de viático fuera del plazo de 5 días hábiles.'
                );
            }

            $this->aplicarTarifaExterior($viatico, $datos);
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

    public function entregarAnticipo(int $viaticoId, User $user): Viatico
    {
        return $this->transicionar($viaticoId, $user, EstadoViatico::CON_ANTICIPO, null, function (Viatico $viatico) use ($user) {
            $this->noSobreElPropio($viatico, $user, 'entregarle el anticipo a');

            if ($this->valor($viatico->modalidad_anticipo) === 'sin_anticipo') {
                throw new ReglaNegocioException(
                    'El viático se solicitó sin anticipo: pase directamente a la comisión.'
                );
            }

            $viatico->monto_anticipo = round((float) $viatico->monto_calculado * 0.70, 2);
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

    public function contabilizar(int $viaticoId, User $user): LiquidacionViatico
    {
        $viatico = $this->transicionar($viaticoId, $user, EstadoViatico::CONTABILIZADO, null, function (Viatico $viatico) use ($user) {
            $this->noSobreElPropio($viatico, $user, 'contabilizar');

            $liquidacion = LiquidacionViatico::where('viatico_id', $viatico->id)->first();

            if (! $liquidacion) {
                throw new ReglaNegocioException('El viático no tiene liquidación registrada.');
            }

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
     * @param list<EstadoViatico>|null $desde Restringe aún más el origen,
     *        cuando el destino se alcanza desde varios estados.
     */
    private function transicionar(
        int $viaticoId,
        User $user,
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

            if ($aplicar) {
                $aplicar($viatico);
            }

            $viatico->estado     = $destino;
            $viatico->updated_by = $user->id;
            $viatico->save();

            ViaticoHistorialEstado::create([
                'viatico_id'      => $viatico->id,
                'estado_anterior' => $origen->value,
                'estado_nuevo'    => $destino->value,
                'motivo'          => $motivo,
                'usuario_id'      => $user->id,
            ]);

            return $viatico->fresh();
        });
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
     * Para el exterior el monto se fija al aprobar, con el coeficiente del
     * país sobre la tarifa base.
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
        $rolPuesto   = $viatico->servidor?->puesto?->rol_puesto;
        $tarifaBase  = $this->valor($rolPuesto) === 'dignatario' ? 220.00 : 185.00;

        $viatico->monto_calculado      = round($tarifaBase * $coeficiente * (float) $viatico->total_dias, 2);
        $viatico->coeficiente_exterior = $coeficiente;
        $viatico->pais_destino         = $datos['pais_destino'] ?? $viatico->pais_destino;
    }

    private function valor(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }
}
