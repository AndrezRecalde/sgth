<?php

namespace App\Services\Nomina;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\EscalaRemuneracionVinculo;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La remuneración de un vínculo a lo largo del tiempo (fases 7.3a y 7.3b): la
 * que tiene hoy, y la que tenía antes de cada cambio, según la fecha en que
 * rige. La cambian los actos —revisión, ascenso, reintegro del ascenso,
 * asunción, adenda del obrero— y la nueva escala, que no tiene acto.
 *
 * La leen las novedades para Financiero. La nómina del SGTH, que pagaba con
 * ella por tramos, se retiró el 2026-10-20.
 */
class RemuneracionDelVinculo
{
    /** Lo que surtió efecto: lo que sigue en trámite todavía no rige. */
    public const EMITIDAS = [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA];

    /** Los actos que cambian la remuneración del vínculo sin crearlo. */
    public const CAMBIAN_LA_REMUNERACION = [
        TipoMovimientoPersonal::REVISION_CLASIFICACION,
        TipoMovimientoPersonal::ASCENSO,
        TipoMovimientoPersonal::ASUNCION_DEFINITIVA,
        TipoMovimientoPersonal::REINTEGRO,
        TipoMovimientoPersonal::INCREMENTO_REMUNERACION,
        TipoMovimientoPersonal::CAMBIO_DENOMINACION,
        TipoMovimientoPersonal::CAMBIO_LUGAR,
    ];

    /** @var array<int, Collection<int, MovimientoPersonal>> */
    private array $actosPorServidor = [];

    /** @var array<int, Collection<int, EscalaRemuneracionVinculo>> */
    private array $escalasPorVinculo = [];

    /** Se vuelve a leer todo: lo que se registró desde la última vez cuenta. */
    public function olvidar(): void
    {
        $this->actosPorServidor = [];
        $this->escalasPorVinculo = [];
    }

    /**
     * La remuneración que el acto deja y la que había. El reintegro de quien no
     * superó la prueba del ascenso lo devuelve a la de antes (LOSEP 17 b.5); el
     * del obrero solo cambia la remuneración si su adenda la cambia.
     *
     * @return array{anterior: float, nueva: float}|null
     */
    public function delActo(MovimientoPersonal $acto): ?array
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
     * Los cambios de remuneración de este vínculo, en el orden en que rigen:
     * los de sus actos y los de la escala.
     *
     * @return Collection<int, array{fecha: CarbonImmutable, anterior: ?float, nueva: float, registro: ?CarbonImmutable}>
     */
    public function cambios(ContratoServidor $vinculo): Collection
    {
        $inicio = $vinculo->fecha_inicio;
        $fin = $vinculo->fecha_fin;

        $deActos = $this->actosDe($vinculo->servidor_id)
            ->filter(fn (MovimientoPersonal $acto) => $acto->fecha_efectiva->gt($inicio)
                && ($fin === null || $acto->fecha_efectiva->lte($fin)))
            ->map(function (MovimientoPersonal $acto) {
                $cambio = $this->delActo($acto);

                return $cambio ? [
                    'fecha'    => CarbonImmutable::parse($acto->fecha_efectiva->toDateString()),
                    'anterior' => $cambio['anterior'],
                    'nueva'    => $cambio['nueva'],
                    'registro' => $acto->fecha_registro ? CarbonImmutable::parse($acto->fecha_registro->toDateString()) : null,
                ] : null;
            })
            ->filter();

        $deEscalas = $this->escalasDe($vinculo->id)->map(fn (EscalaRemuneracionVinculo $fila) => [
            'fecha'    => CarbonImmutable::parse($fila->escala->rige_desde->toDateString()),
            'anterior' => $fila->remuneracion_anterior !== null ? (float) $fila->remuneracion_anterior : null,
            'nueva'    => (float) $fila->remuneracion_nueva,
            'registro' => CarbonImmutable::parse($fila->escala->registrada_en->toDateString()),
        ]);

        return $deActos->merge($deEscalas)
            ->sortBy(fn (array $c) => $c['fecha']->toDateString())
            ->values();
    }

    /**
     * La remuneración del vínculo en una fecha: la de hoy, salvo que un cambio
     * posterior la haya movido, y entonces la que ese cambio encontró.
     */
    public function al(ContratoServidor $vinculo, ?CarbonInterface $fecha): ?float
    {
        $actual = $vinculo->remuneracion !== null ? (float) $vinculo->remuneracion : null;

        if ($fecha === null) {
            return $actual;
        }

        $posterior = $this->cambios($vinculo)->first(fn (array $c) => $c['fecha']->gt($fecha));

        return $posterior ? ($posterior['anterior'] ?? $actual) : $actual;
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function actosDe(int $servidorId): Collection
    {
        return $this->actosPorServidor[$servidorId] ??= MovimientoPersonal::query()
            ->where('servidor_id', $servidorId)
            ->whereIn('tipo_movimiento', array_map(fn ($t) => $t->value, self::CAMBIAN_LA_REMUNERACION))
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereNotNull('fecha_efectiva')
            ->with('movimientoRelacionado')
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, EscalaRemuneracionVinculo> */
    private function escalasDe(int $contratoId): Collection
    {
        return $this->escalasPorVinculo[$contratoId] ??= EscalaRemuneracionVinculo::query()
            ->where('contrato_id', $contratoId)
            ->whereHas('escala', fn (Builder $q) => $q->whereNull('revertida_en'))
            ->with('escala')
            ->get();
    }
}
