<?php

namespace App\Services\Nomina;

use App\Enums\ConceptoNovedadNomina;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\EstadoSubrogacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSancion;
use App\Enums\TipoSubrogacion;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Estructura\EscalaRemuneracionVinculo;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Subrogacion;
use App\Services\Expediente\OcupantesDelPuesto;
use App\Services\Expediente\TerminacionObreroService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las novedades de un mes, derivadas de los actos (diseño, 9.8; fase 7.3a).
 * No se guardan: como la ausencia, salen de lo registrado cada vez, así que un
 * reintegro que acorta una licencia o un acto que se anula cambian el mes sin
 * que nadie tenga que corregir una fila. Lo que se congela es la entrega
 * (`NovedadesNominaService`).
 *
 * Cada monto sale de las remuneraciones que el acto congeló al crearse, como el
 * descuento de la multa: el documento firmado y la novedad dicen la misma
 * cifra. La entrada y la salida, que no tienen acto que la congele, toman la
 * del vínculo de ese día.
 *
 * Servicios Profesionales queda fuera: es un contrato civil, y sus honorarios
 * se pagan contra factura, no en el rol (TH N6).
 *
 * @phpstan-type Novedad array{
 *     clave: string, concepto: ConceptoNovedadNomina, mes_afectado: string, es_ajuste: bool, vigente: bool,
 *     servidor_id: int, servidor: string, cedula: ?string, contrato_id: ?int, movimiento_id: ?int,
 *     subrogacion_id: ?int, acto: ?string, desde: ?string, hasta: ?string, dias: ?int,
 *     base: ?float, monto: ?float, detalle: string
 * }
 */
class NovedadesDelMes
{
    /** Las que surtieron efecto: lo que sigue en trámite todavía no rige. */
    private const EMITIDAS = [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA];

    /** Los actos que cambian la remuneración del vínculo sin crearlo. */
    private const CAMBIAN_LA_REMUNERACION = [
        TipoMovimientoPersonal::REVISION_CLASIFICACION,
        TipoMovimientoPersonal::ASCENSO,
        TipoMovimientoPersonal::ASUNCION_DEFINITIVA,
        TipoMovimientoPersonal::REINTEGRO,
        TipoMovimientoPersonal::INCREMENTO_REMUNERACION,
        TipoMovimientoPersonal::CAMBIO_DENOMINACION,
        TipoMovimientoPersonal::CAMBIO_LUGAR,
    ];

    /** @var array<int, Collection<int, MovimientoPersonal>> */
    private array $cambiosPorServidor = [];

    /** @var array<int, Collection<int, EscalaRemuneracionVinculo>> */
    private array $escalasPorVinculo = [];

    public function __construct(
        private readonly TerminacionObreroService $terminaciones,
    ) {
    }

    /** @return Collection<int, Novedad> */
    public function de(MesDeNomina $mes): Collection
    {
        $this->cambiosPorServidor = [];
        $this->escalasPorVinculo = [];

        return collect()
            ->merge($this->ingresosYSalidas($mes))
            ->merge($this->cambiosDeRemuneracion($mes))
            ->merge($this->escalas($mes))
            ->merge($this->ausenciasSinRemuneracion($mes))
            ->merge($this->suspensiones($mes))
            ->merge($this->subrogaciones($mes))
            ->merge($this->multas($mes))
            ->merge($this->remuneracionesPendientes($mes))
            ->sortBy([
                ['servidor', 'asc'],
                fn (array $a, array $b) => array_search($a['concepto'], ConceptoNovedadNomina::cases(), true)
                    <=> array_search($b['concepto'], ConceptoNovedadNomina::cases(), true),
                ['desde', 'asc'],
                ['clave', 'asc'],
            ])
            ->values();
    }

    // ── La entrada y la salida ──────────────────────────────────────

    /**
     * Quien entra y quien sale en el mes. El vínculo lo crea y lo cierra el
     * motor el día en que rige el acto (fase 1.6): un ingreso o una renuncia
     * registrados para el 25 todavía no están en el vínculo el 20, cuando se
     * preparan las novedades, así que también se leen de su acto. La clave es
     * la del acto, para que no cambie cuando el motor lo aplique.
     *
     * @return list<Novedad>
     */
    private function ingresosYSalidas(MesDeNomina $mes): array
    {
        $inicio = $mes->inicio->toDateString();
        $fin = $mes->fin->toDateString();

        $porRegir = fn () => MovimientoPersonal::query()
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereNull('efecto_aplicado_en')
            ->whereBetween('fecha_efectiva', [$inicio, $fin]);

        $cierresPorRegir = $porRegir()->orderBy('fecha_efectiva')->orderBy('id')->get()
            ->filter(fn (MovimientoPersonal $acto) => $acto->cierraElVinculo())
            ->keyBy('servidor_id');

        $vinculos = ContratoServidor::query()
            ->where('tipo_nombramiento', '!=', TipoNombramiento::SERVICIOS_PROFESIONALES->value)
            ->where('estado', '!=', EstadoContrato::CANCELADO->value)
            ->where(fn (Builder $q) => $q
                ->whereBetween('fecha_inicio', [$inicio, $fin])
                ->orWhere(fn (Builder $s) => $s->whereBetween('fecha_fin', [$inicio, $fin])->where('fecha_inicio', '<', $inicio))
                ->orWhere(fn (Builder $v) => $v->where('estado', EstadoContrato::VIGENTE->value)
                    ->whereIn('servidor_id', $cierresPorRegir->keys()->all())))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'movimientoOrigen', 'movimientoCierre'])
            ->orderBy('id')
            ->get();

        $novedades = [];

        foreach ($vinculos as $vinculo) {
            $cierre = $vinculo->estado === EstadoContrato::VIGENTE ? $cierresPorRegir->get($vinculo->servidor_id) : null;
            if ($cierre && $vinculo->fecha_fin && $vinculo->fecha_fin->lt($cierre->fecha_efectiva)) {
                $cierre = null;
            }

            $novedad = $this->entradaOSalida(
                $mes,
                servidor: $vinculo->servidor,
                contratoId: $vinculo->id,
                desde: $vinculo->fecha_inicio,
                hasta: $cierre?->fecha_efectiva ?? $vinculo->fecha_fin,
                actoDeEntrada: $vinculo->movimientoOrigen,
                actoDeSalida: $cierre ?? $vinculo->movimientoCierre,
                motivoDeSalida: $vinculo->motivo_fin,
                base: fn (bool $entra) => $this->remuneracionAl($vinculo, $entra ? $vinculo->fecha_inicio : $vinculo->fecha_fin),
                continuo: fn () => $this->vieneDeOtroVinculo($vinculo),
            );

            if ($novedad) {
                $novedades[] = $novedad;
            }
        }

        // El ingreso registrado que todavía no rige: su vínculo aún no existe.
        $ingresosPorRegir = $porRegir()
            ->whereIn('tipo_movimiento', array_map(
                fn (TipoMovimientoPersonal $t) => $t->value,
                array_filter(TipoMovimientoPersonal::cases(), fn (TipoMovimientoPersonal $t) => $t->creaVinculo()),
            ))
            ->where(fn (Builder $q) => $q->whereNull('tipo_nombramiento_propuesto')
                ->orWhere('tipo_nombramiento_propuesto', '!=', TipoNombramiento::SERVICIOS_PROFESIONALES->value))
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();

        foreach ($ingresosPorRegir as $acto) {
            $novedad = $this->entradaOSalida(
                $mes,
                servidor: $acto->servidor,
                contratoId: null,
                desde: $acto->fecha_efectiva,
                hasta: $acto->fecha_fin_propuesta,
                actoDeEntrada: $acto,
                actoDeSalida: null,
                motivoDeSalida: null,
                base: fn () => $acto->remuneracion_propuesta !== null ? (float) $acto->remuneracion_propuesta : null,
                continuo: fn () => false,
            );

            if ($novedad) {
                $novedades[] = $novedad;
            }
        }

        return $novedades;
    }

    /**
     * La fila de quien entra o sale en el mes. Si entra y sale en el mismo,
     * es una sola: el ingreso, con los días que estuvo.
     *
     * @param  \Closure(bool): ?float  $base
     * @param  \Closure(): bool  $continuo
     * @return Novedad|null
     */
    private function entradaOSalida(
        MesDeNomina $mes,
        ?\App\Models\Expediente\Servidor $servidor,
        ?int $contratoId,
        CarbonInterface $desde,
        ?CarbonInterface $hasta,
        ?MovimientoPersonal $actoDeEntrada,
        ?MovimientoPersonal $actoDeSalida,
        ?string $motivoDeSalida,
        \Closure $base,
        \Closure $continuo,
    ): ?array {
        $entra = $mes->contiene($desde);
        $sale = $mes->contiene($hasta);

        if (! $entra && ! $sale) {
            return null;
        }

        $dias = $mes->dias($entra ? $desde : $mes->inicio, $hasta);
        $remuneracion = $base($entra);
        $motivo = $actoDeSalida?->causal()?->etiqueta() ?? (filled($motivoDeSalida) ? $this->sinPuntoFinal($motivoDeSalida) : null);

        $detalle = $entra
            ? 'Ingresa el '.$desde->format('d/m/Y')
                .($continuo() ? ', sin interrupción desde su vínculo anterior' : '')
                .($sale ? ' y sale el '.$hasta->format('d/m/Y') : '')
            : 'Sale el '.$hasta->format('d/m/Y');

        if ($sale && $motivo) {
            $detalle .= " ({$motivo})";
        }

        $acto = $entra ? $actoDeEntrada : $actoDeSalida;
        $origen = $acto ? "movimiento:{$acto->id}" : "contrato:{$contratoId}";

        return $this->novedad(
            $mes,
            clave: ($entra ? 'ingreso' : 'salida').":{$origen}",
            concepto: $entra ? ConceptoNovedadNomina::INGRESO : ConceptoNovedadNomina::SALIDA,
            servidor: $servidor,
            detalle: "{$detalle}: {$this->diasEnTexto($dias)} del mes.",
            contratoId: $contratoId,
            movimiento: $acto,
            desde: $entra ? $desde : $mes->inicio,
            hasta: $sale ? $hasta : $mes->fin,
            dias: $dias,
            base: $remuneracion,
            monto: $remuneracion !== null ? round($remuneracion * $dias / MesDeNomina::DIAS, 2) : null,
        );
    }

    /** ¿Empieza el día siguiente al fin de otro de sus vínculos? Es servicio continuo. */
    private function vieneDeOtroVinculo(ContratoServidor $vinculo): bool
    {
        return ContratoServidor::where('servidor_id', $vinculo->servidor_id)
            ->whereKeyNot($vinculo->id)
            ->where('estado', '!=', EstadoContrato::CANCELADO->value)
            ->whereDate('fecha_fin', $vinculo->fecha_inicio->copy()->subDay()->toDateString())
            ->exists();
    }

    // ── El cambio de remuneración ───────────────────────────────────

    /**
     * El cambio de remuneración, en el mes en que rige. Si se registró en un
     * mes posterior, cada mes hasta el de su registro lleva también la
     * diferencia: el mes ya entregado recibe así su ajuste, y no solo el
     * primero (fase 7.3b).
     *
     * @return list<Novedad>
     */
    private function cambiosDeRemuneracion(MesDeNomina $mes): array
    {
        $inicio = $mes->inicio->toDateString();

        $actos = MovimientoPersonal::query()
            ->whereIn('tipo_movimiento', array_map(fn ($t) => $t->value, self::CAMBIAN_LA_REMUNERACION))
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereDate('fecha_efectiva', '<=', $mes->fin->toDateString())
            ->where(fn (Builder $q) => $q->whereDate('fecha_efectiva', '>=', $inicio)->orWhereDate('fecha_registro', '>=', $inicio))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'movimientoRelacionado'])
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();

        $novedades = [];

        foreach ($actos as $acto) {
            $cambio = $this->remuneracionesDelCambio($acto);

            if ($cambio === null || abs($cambio['nueva'] - $cambio['anterior']) < 0.005) {
                continue;
            }

            $novedades[] = $this->cambioEnElMes(
                $mes,
                clave: "cambio:movimiento:{$acto->id}",
                servidor: $acto->servidor,
                que: $acto->etiqueta(),
                rige: $acto->fecha_efectiva,
                registro: $acto->fecha_registro,
                anterior: $cambio['anterior'],
                nueva: $cambio['nueva'],
                movimiento: $acto,
            );
        }

        return $novedades;
    }

    /**
     * La nueva escala de remuneraciones (fase 7.3b): un cambio de remuneración
     * por vínculo, sin acción de personal. Como el de un acto, si se registró
     * después del mes en que rige, cada mes hasta el de su registro.
     *
     * @return list<Novedad>
     */
    private function escalas(MesDeNomina $mes): array
    {
        $inicio = $mes->inicio->toDateString();

        $filas = EscalaRemuneracionVinculo::query()
            ->whereHas('escala', fn (Builder $q) => $q
                ->whereNull('revertida_en')
                ->whereDate('rige_desde', '<=', $mes->fin->toDateString())
                ->where(fn (Builder $r) => $r->whereDate('rige_desde', '>=', $inicio)->orWhereDate('registrada_en', '>=', $inicio)))
            ->with([
                'escala.grados',
                'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
                'contrato:id,fecha_inicio,fecha_fin',
            ])
            ->orderBy('id')
            ->get();

        $novedades = [];

        foreach ($filas as $fila) {
            $escala = $fila->escala;
            // Sin remuneración guardada, el vínculo cobraba la del grado.
            $anterior = $fila->remuneracion_anterior !== null
                ? (float) $fila->remuneracion_anterior
                : (float) $escala->grados->firstWhere('grupo_ocupacional_id', $fila->grupo_ocupacional_id)?->rmu_anterior;

            $novedad = $this->cambioEnElMes(
                $mes,
                clave: "escala:{$fila->id}",
                servidor: $fila->servidor,
                que: "Nueva escala de remuneraciones ({$escala->instrumento})",
                rige: $escala->rige_desde,
                registro: $escala->registrada_en,
                anterior: $anterior,
                nueva: (float) $fila->remuneracion_nueva,
                contratoId: $fila->contrato_id,
                acto: $escala->instrumento,
                inicioDelVinculo: $fila->contrato?->fecha_inicio,
                finDelVinculo: $fila->contrato?->fecha_fin,
            );

            if ($novedad) {
                $novedades[] = $novedad;
            }
        }

        return $novedades;
    }

    /**
     * La diferencia de un cambio de remuneración en el mes: desde el día en
     * que rige, si es este mes, o el mes entero si es uno posterior que todavía
     * la debía.
     *
     * @return Novedad|null
     */
    private function cambioEnElMes(
        MesDeNomina $mes,
        string $clave,
        ?\App\Models\Expediente\Servidor $servidor,
        string $que,
        CarbonInterface $rige,
        ?CarbonInterface $registro,
        float $anterior,
        float $nueva,
        ?MovimientoPersonal $movimiento = null,
        ?int $contratoId = null,
        ?string $acto = null,
        ?CarbonInterface $inicioDelVinculo = null,
        ?CarbonInterface $finDelVinculo = null,
    ): ?array {
        $enEsteMes = $mes->contiene($rige);
        $desde = $enEsteMes ? $rige : $mes->inicio;

        if ($inicioDelVinculo && $inicioDelVinculo->gt($desde)) {
            $desde = $inicioDelVinculo;
        }

        $dias = $mes->dias($desde, $finDelVinculo);

        if ($dias === 0) {
            return null;
        }

        $detalle = "{$que}: desde el {$rige->format('d/m/Y')} cobra {$this->dinero($nueva)} (antes {$this->dinero($anterior)})";
        $detalle .= $enEsteMes
            ? "; la diferencia de {$this->diasEnTexto($dias)}."
            : '; se registró el '.($registro?->format('d/m/Y') ?? '—').", y este mes le debe la diferencia de {$this->diasEnTexto($dias)}.";

        $novedad = $this->novedad(
            $mes,
            clave: $clave,
            concepto: ConceptoNovedadNomina::CAMBIO_REMUNERACION,
            servidor: $servidor,
            detalle: $detalle,
            contratoId: $contratoId,
            movimiento: $movimiento,
            desde: $desde,
            hasta: $finDelVinculo && $finDelVinculo->lt($mes->fin) ? $finDelVinculo : $mes->fin,
            dias: $dias,
            base: $nueva,
            monto: round(($nueva - $anterior) * $dias / MesDeNomina::DIAS, 2),
        );

        if ($acto !== null) {
            $novedad['acto'] = $acto;
        }

        return $novedad;
    }

    /**
     * La remuneración que el acto deja y la que había. El reintegro de quien no
     * superó la prueba del ascenso lo devuelve a la de antes (LOSEP 17 b.5); el
     * del obrero solo cambia la remuneración si su adenda la cambia.
     *
     * @return array{anterior: float, nueva: float}|null
     */
    private function remuneracionesDelCambio(MovimientoPersonal $acto): ?array
    {
        if ($acto->tipo_movimiento === TipoMovimientoPersonal::REINTEGRO) {
            if (! $acto->esReintegroDelAscenso()) {
                return null;
            }

            $ascenso = $acto->movimientoRelacionado;
            $anterior = $acto->remuneracion_origen ?? $ascenso?->remuneracion_propuesta;
            $nueva = $ascenso?->remuneracion_origen;
        } else {
            if (in_array($acto->tipo_movimiento, [
                TipoMovimientoPersonal::INCREMENTO_REMUNERACION,
                TipoMovimientoPersonal::CAMBIO_DENOMINACION,
                TipoMovimientoPersonal::CAMBIO_LUGAR,
            ], true) && ! $acto->cambiaElContratoDeTrabajo()) {
                return null;
            }

            $anterior = $acto->remuneracion_origen;
            $nueva = $acto->remuneracion_propuesta;
        }

        return $anterior !== null && $nueva !== null
            ? ['anterior' => (float) $anterior, 'nueva' => (float) $nueva]
            : null;
    }

    /**
     * La remuneración del vínculo en una fecha: la de hoy, salvo que un acto
     * posterior la haya cambiado, y entonces la que ese acto encontró.
     */
    private function remuneracionAl(ContratoServidor $vinculo, ?CarbonInterface $fecha): ?float
    {
        $actual = $vinculo->remuneracion !== null ? (float) $vinculo->remuneracion : null;

        if ($fecha === null) {
            return $actual;
        }

        $posterior = $this->cambiosDe($vinculo->servidor_id)
            ->first(function (MovimientoPersonal $acto) use ($vinculo, $fecha) {
                return $acto->fecha_efectiva->gt($fecha)
                    && $acto->fecha_efectiva->gte($vinculo->fecha_inicio)
                    && ($vinculo->fecha_fin === null || $acto->fecha_efectiva->lte($vinculo->fecha_fin))
                    && $this->remuneracionesDelCambio($acto) !== null;
            });

        // La nueva escala también la cambia, sin acto (fase 7.3b).
        $escala = $this->escalasDe($vinculo->id)
            ->first(fn (EscalaRemuneracionVinculo $fila) => $fila->escala->rige_desde->gt($fecha));

        if ($escala && (! $posterior || $escala->escala->rige_desde->lt($posterior->fecha_efectiva))) {
            return $escala->remuneracion_anterior !== null ? (float) $escala->remuneracion_anterior : $actual;
        }

        return $posterior ? $this->remuneracionesDelCambio($posterior)['anterior'] : $actual;
    }

    /** @return Collection<int, EscalaRemuneracionVinculo> */
    private function escalasDe(int $contratoId): Collection
    {
        return $this->escalasPorVinculo[$contratoId] ??= EscalaRemuneracionVinculo::query()
            ->where('contrato_id', $contratoId)
            ->whereHas('escala', fn (Builder $q) => $q->whereNull('revertida_en'))
            ->with('escala')
            ->get()
            ->sortBy(fn (EscalaRemuneracionVinculo $fila) => $fila->escala->rige_desde->toDateString())
            ->values();
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function cambiosDe(int $servidorId): Collection
    {
        return $this->cambiosPorServidor[$servidorId] ??= MovimientoPersonal::query()
            ->where('servidor_id', $servidorId)
            ->whereIn('tipo_movimiento', array_map(fn ($t) => $t->value, self::CAMBIAN_LA_REMUNERACION))
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereNotNull('fecha_efectiva')
            ->with('movimientoRelacionado')
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();
    }

    // ── Los días sin remuneración ───────────────────────────────────

    /**
     * La licencia y la comisión sin remuneración (LOSEP Arts. 28 y 31), y la
     * licencia del Consejo que no la conserva: hasta su fin, o hasta la víspera
     * del reintegro, si volvió antes (fase 2.4).
     *
     * @return list<Novedad>
     */
    private function ausenciasSinRemuneracion(MesDeNomina $mes): array
    {
        $actos = MovimientoPersonal::query()
            ->esAusenciaTemporal()
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereDate('fecha_inicio', '<=', $mes->fin->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $mes->inicio->toDateString()))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'reintegro'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->filter(fn (MovimientoPersonal $acto) => $this->esSinRemuneracion($acto));

        $novedades = [];

        foreach ($actos as $acto) {
            $hasta = $acto->finEfectivo();
            $recorte = $mes->recorte($acto->fecha_inicio, $hasta);

            if (! $recorte) {
                continue;
            }

            $dias = $mes->dias($acto->fecha_inicio, $hasta);
            $base = $acto->remuneracion_origen !== null ? (float) $acto->remuneracion_origen : null;

            $novedades[] = $this->novedad(
                $mes,
                clave: "sin_remuneracion:movimiento:{$acto->id}",
                concepto: ConceptoNovedadNomina::DIAS_SIN_REMUNERACION,
                servidor: $acto->servidor,
                detalle: "{$acto->etiquetaAusencia()}: del {$recorte[0]->format('d/m/Y')} al {$recorte[1]->format('d/m/Y')}"
                    .($hasta === null ? ' (sin fecha de fin)' : '')
                    .", {$this->diasEnTexto($dias)} sin remuneración.",
                movimiento: $acto,
                desde: $recorte[0],
                hasta: $recorte[1],
                dias: $dias,
                base: $base,
                monto: $base !== null ? -round($base * $dias / MesDeNomina::DIAS, 2) : null,
            );
        }

        return $novedades;
    }

    private function esSinRemuneracion(MovimientoPersonal $acto): bool
    {
        return match (true) {
            $acto->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION => true,
            $acto->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_CONSEJO          => $acto->con_remuneracion === false,
            default => $acto->subtipoEfectivo() === SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION
                || ($acto->subtipo_movimiento === null && $acto->tipo_movimiento === TipoMovimientoPersonal::COMISION_SIN_REMUNERACION),
        };
    }

    /**
     * La suspensión temporal sin goce de remuneración (LOSEP Art. 43 c): los
     * días calendario desde que rige, como el descuento que ya se le avisa a
     * Financiero (#344).
     *
     * @return list<Novedad>
     */
    private function suspensiones(MesDeNomina $mes): array
    {
        $novedades = [];

        // La suspensión dura hasta treinta días (LOSEP Art. 43 c): la que rige
        // hasta dos meses antes todavía puede tocar este.
        foreach ($this->sanciones(TipoSancion::SUSPENSION, $mes->inicio->subMonths(2), $mes->fin) as $sancion) {
            $acto = $sancion->movimientoPersonal;
            $desde = $sancion->fecha_efectiva ?? $acto->fecha_efectiva;
            $diasSancion = (int) $sancion->dias_suspension;

            if (! $desde || $diasSancion < 1) {
                continue;
            }

            $hasta = $desde->copy()->addDays($diasSancion - 1);
            $recorte = $mes->recorte($desde, $hasta);

            if (! $recorte) {
                continue;
            }

            $dias = $mes->dias($desde, $hasta);
            $base = $acto->remuneracion_origen !== null ? (float) $acto->remuneracion_origen : null;

            $novedades[] = $this->novedad(
                $mes,
                clave: "suspension:movimiento:{$acto->id}",
                concepto: ConceptoNovedadNomina::DIAS_SIN_REMUNERACION,
                servidor: $acto->servidor,
                detalle: "Suspensión temporal sin goce de remuneración: del {$recorte[0]->format('d/m/Y')} al "
                    ."{$recorte[1]->format('d/m/Y')}, {$this->diasEnTexto($dias)} sin remuneración.",
                movimiento: $acto,
                desde: $recorte[0],
                hasta: $recorte[1],
                dias: $dias,
                base: $base,
                monto: $base !== null ? -round($base * $dias / MesDeNomina::DIAS, 2) : null,
            );
        }

        return $novedades;
    }

    // ── La subrogación y el encargo ─────────────────────────────────

    /**
     * La diferencia entre la remuneración que el subrogante ya percibe y la del
     * puesto que asume (LOSEP Arts. 126 y 127): las dos que el acto congeló, que
     * son las que dice la acción de personal. También la del Viceprefecto, que
     * es la misma fila (fase 4.1b).
     *
     * Cuenta mientras surtió efecto: hasta su fin, o hasta la víspera del día en
     * que se canceló.
     *
     * @return list<Novedad>
     */
    private function subrogaciones(MesDeNomina $mes): array
    {
        $subrogaciones = Subrogacion::query()
            // También la pendiente: con su acción registrada, se activa sola el
            // día en que empieza, y el mes ya tiene que contarla.
            ->whereIn('estado', array_map(fn (EstadoSubrogacion $e) => $e->value, EstadoSubrogacion::cases()))
            ->whereHas('movimientoPersonal', fn (Builder $q) => $q->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS)))
            ->whereDate('fecha_inicio', '<=', $mes->fin->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $mes->inicio->toDateString()))
            ->with([
                'subrogante:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
                'subrogado:id,nombre,segundo_nombre,apellido,segundo_apellido',
                'puestoSubrogado.cargo:id,nombre',
                'movimientoPersonal',
            ])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get();

        $novedades = [];

        foreach ($subrogaciones as $subrogacion) {
            $hasta = $subrogacion->fecha_fin;

            if ($subrogacion->estado === EstadoSubrogacion::CANCELADA) {
                // Las canceladas antes de que quedara dicho cuándo no se sabe
                // cuánto duraron: Talento Humano las revisa a mano.
                if (! $subrogacion->cancelada_en) {
                    continue;
                }

                $vispera = $subrogacion->cancelada_en->copy()->subDay();
                $hasta = $hasta === null || $vispera->lt($hasta) ? $vispera : $hasta;
            }

            $recorte = $mes->recorte($subrogacion->fecha_inicio, $hasta);

            if (! $recorte) {
                continue;
            }

            $acto = $subrogacion->movimientoPersonal;
            $diferencia = $acto->remuneracion_propuesta !== null && $acto->remuneracion_origen !== null
                ? (float) $acto->remuneracion_propuesta - (float) $acto->remuneracion_origen
                : null;

            // Si el subrogante ya cobra lo mismo o más, no hay diferencia que pagar.
            if ($diferencia !== null && $diferencia < 0.005) {
                continue;
            }

            $dias = $mes->dias($subrogacion->fecha_inicio, $hasta);
            $encargo = $subrogacion->tipo === TipoSubrogacion::ENCARGO;
            $puesto = $subrogacion->puestoSubrogado?->cargo?->nombre ?? 'el puesto';

            $novedades[] = $this->novedad(
                $mes,
                clave: "subrogacion:{$subrogacion->id}",
                concepto: $encargo ? ConceptoNovedadNomina::ENCARGO : ConceptoNovedadNomina::SUBROGACION,
                servidor: $subrogacion->subrogante,
                detalle: ($encargo
                    ? "Encargo de {$puesto}"
                    : 'Subroga a '.OcupantesDelPuesto::nombre($subrogacion->subrogado)." en {$puesto}")
                    .": del {$recorte[0]->format('d/m/Y')} al {$recorte[1]->format('d/m/Y')}, {$this->diasEnTexto($dias)}"
                    .($diferencia !== null ? ", con una diferencia de {$this->dinero($diferencia)} al mes." : '; el acto no tiene las remuneraciones que se comparan.'),
                movimiento: $acto,
                subrogacionId: $subrogacion->id,
                desde: $recorte[0],
                hasta: $recorte[1],
                dias: $dias,
                base: $diferencia,
                monto: $diferencia !== null ? round($diferencia * $dias / MesDeNomina::DIAS, 2) : null,
            );
        }

        return $novedades;
    }

    // ── La multa ────────────────────────────────────────────────────

    /**
     * La sanción pecuniaria, en el mes en que rige: el porcentaje sobre la
     * remuneración que la acción congeló, el mismo que se le avisa a Financiero
     * al registrarla (#344). También la del obrero, con su hoja de registro
     * interno (fase 4.4).
     *
     * @return list<Novedad>
     */
    private function multas(MesDeNomina $mes): array
    {
        $novedades = [];

        foreach ($this->sanciones(TipoSancion::MULTA, $mes->inicio, $mes->fin) as $sancion) {
            $acto = $sancion->movimientoPersonal;
            $base = $acto->remuneracion_origen !== null ? (float) $acto->remuneracion_origen : null;
            $descuento = $sancion->descuentoReferencial($base);

            $novedades[] = $this->novedad(
                $mes,
                clave: "multa:movimiento:{$acto->id}",
                concepto: ConceptoNovedadNomina::MULTA,
                servidor: $acto->servidor,
                detalle: 'Multa del '.($descuento['detalle'] ?? 'porcentaje de la remuneración').'.',
                movimiento: $acto,
                desde: $sancion->fecha_efectiva ?? $acto->fecha_efectiva,
                hasta: null,
                dias: null,
                base: $base,
                monto: isset($descuento['monto']) ? -$descuento['monto'] : null,
            );
        }

        return $novedades;
    }

    /**
     * Las sanciones de un tipo cuya acción surtió efecto y que rigen en el
     * período.
     *
     * @return Collection<int, SancionDisciplinaria>
     */
    private function sanciones(TipoSancion $tipo, CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return SancionDisciplinaria::query()
            ->where('tipo_sancion', $tipo->value)
            // La que una sentencia dejó sin efecto ya no se descuenta.
            ->whereNull('anulada_en')
            ->whereHas('movimientoPersonal', fn (Builder $q) => $q
                ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
                ->whereBetween('fecha_efectiva', [$desde->toDateString(), $hasta->toDateString()]))
            ->with('movimientoPersonal.servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    // ── Lo que se debe por el tiempo fuera ──────────────────────────

    /**
     * La restitución por sentencia paga las remuneraciones dejadas de percibir
     * que fija el juez (LOSEP Art. 46; fase 2.6); el reintegro del obrero, las
     * pendientes con el recargo del 10 % (CT 195.3; fase 4.5b), y solo si la
     * sentencia declaró la ineficacia del despido.
     *
     * @return list<Novedad>
     */
    private function remuneracionesPendientes(MesDeNomina $mes): array
    {
        $actos = MovimientoPersonal::query()
            ->where('tipo_movimiento', TipoMovimientoPersonal::RESTITUCION->value)
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereBetween('fecha_efectiva', [$mes->inicio->toDateString(), $mes->fin->toDateString()])
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'movimientoRelacionado'])
            ->orderBy('id')
            ->get();

        $novedades = [];

        foreach ($actos as $acto) {
            if ($acto->esReintegroPorDespidoIneficaz()) {
                $liquidacion = $this->terminaciones->liquidacion($acto);
                $conSentencia = $acto->fecha_sentencia !== null;
                $monto = $conSentencia && isset($liquidacion['total']) ? (float) $liquidacion['total'] : null;
                $detalle = 'Remuneraciones pendientes y el recargo del 10 % (Código del Trabajo, Art. 195.3)'
                    .($conSentencia ? '.' : ': lo ordenó la providencia, y se pagan si la sentencia declara la ineficacia del despido.');
            } else {
                $monto = $acto->remuneraciones_dejadas_de_percibir !== null ? (float) $acto->remuneraciones_dejadas_de_percibir : null;
                $detalle = 'Remuneraciones dejadas de percibir que fija la sentencia (LOSEP, Art. 46).';
            }

            $novedades[] = $this->novedad(
                $mes,
                clave: "restitucion:movimiento:{$acto->id}",
                concepto: ConceptoNovedadNomina::REMUNERACIONES_PENDIENTES,
                servidor: $acto->servidor,
                detalle: $detalle,
                movimiento: $acto,
                desde: $acto->fecha_efectiva,
                hasta: null,
                dias: null,
                base: null,
                monto: $monto !== null ? round($monto, 2) : null,
            );
        }

        return $novedades;
    }

    // ── La fila ─────────────────────────────────────────────────────

    /** @return Novedad */
    private function novedad(
        MesDeNomina $mes,
        string $clave,
        ConceptoNovedadNomina $concepto,
        ?\App\Models\Expediente\Servidor $servidor,
        string $detalle,
        ?int $contratoId = null,
        ?MovimientoPersonal $movimiento = null,
        ?int $subrogacionId = null,
        ?CarbonInterface $desde = null,
        ?CarbonInterface $hasta = null,
        ?int $dias = null,
        ?float $base = null,
        ?float $monto = null,
    ): array {
        return [
            'clave'          => $clave,
            'concepto'       => $concepto,
            'mes_afectado'   => $mes->periodo,
            'es_ajuste'      => false,
            'vigente'        => true,
            'servidor_id'    => (int) $servidor?->id,
            'servidor'       => OcupantesDelPuesto::nombre($servidor),
            'cedula'         => $servidor?->cedula,
            'contrato_id'    => $contratoId,
            'movimiento_id'  => $movimiento?->id,
            'subrogacion_id' => $subrogacionId,
            'acto'           => $movimiento?->codigo_registro,
            'desde'          => $desde?->toDateString(),
            'hasta'          => $hasta?->toDateString(),
            'dias'           => $dias,
            'base'           => $base !== null ? round($base, 2) : null,
            'monto'          => $monto,
            'detalle'        => $detalle,
        ];
    }

    private function diasEnTexto(int $dias): string
    {
        return $dias === 1 ? '1 día' : "{$dias} días";
    }

    private function dinero(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }

    private function sinPuntoFinal(string $texto): string
    {
        return rtrim(trim($texto), '.');
    }
}
