<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SupuestoProvisional;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * El período de prueba y su nombramiento definitivo (LOSEP Art. 17 b.5;
 * Reglamento 224–227; diseño de Acciones de Personal, 4.2 y 9.2; TH N2 y N3;
 * fases 3.2 y 3.3).
 *
 * Quien gana un concurso entra con nombramiento provisional de prueba. A los
 * tres meses, «superado el cual, o, en caso de no haberse practicado, se
 * otorgará el nombramiento definitivo». La evaluación del período de prueba
 * todavía no existe (diseño, 9.3), así que el sistema prepara siempre el
 * definitivo en borrador; si Talento Humano juzga que no superó la prueba,
 * anula ese borrador y registra la cesación.
 *
 * Quien asciende tiene seis meses de prueba con su puesto anterior reservado.
 * Si no la supera no cesa: vuelve a ese puesto con el reintegro, que se prepara
 * desde la misma pantalla.
 */
class PeriodoDePruebaService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
    ) {
    }

    /**
     * Quién está en prueba, por qué, hasta cuándo y cuántos días le quedan.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $hoy = now()->startOfDay();

        return $this->enPrueba()
            ->with([
                'servidor:id,nombre,apellido,cedula', 'puesto.cargo:id,nombre', 'unidadAdministrativa:id,nombre',
                'puestoReservado.cargo:id,nombre', 'movimientoAscenso',
            ])
            ->get()
            ->map(function (ContratoServidor $contrato) use ($hoy) {
                $fin = $contrato->finDelPeriodoDePrueba();
                $definitivo = $this->definitivoDe($contrato);
                $reintegro = $contrato->movimientoAscenso?->reintegro()->first();

                return [
                    'contrato_id'      => $contrato->id,
                    'servidor'         => [
                        'id'     => $contrato->servidor?->id,
                        'nombre' => trim(($contrato->servidor?->apellido ?? '').' '.($contrato->servidor?->nombre ?? '')),
                        'cedula' => $contrato->servidor?->cedula,
                    ],
                    // Por qué está en prueba: ingresó por concurso o ascendió.
                    'motivo'           => $contrato->supuesto_provisional->value,
                    'motivo_etiqueta'  => $contrato->supuesto_provisional->etiqueta(),
                    'puesto'           => $contrato->puesto?->cargo?->nombre,
                    'unidad'           => $contrato->unidadAdministrativa?->nombre,
                    // El del ascendido, que se le guarda mientras dura la prueba.
                    'puesto_reservado' => $contrato->puestoReservado?->cargo?->nombre,
                    'desde'            => $contrato->inicioDelPeriodoDePrueba()?->toDateString(),
                    'hasta'            => $fin?->toDateString(),
                    'dias_restantes'   => $fin ? (int) $hoy->diffInDays($fin, false) : null,
                    'concluida'        => $fin !== null && $fin->lt($hoy),
                    'definitivo'       => $this->resumen($definitivo),
                    'reintegro'        => $this->resumen($reintegro),
                ];
            })
            ->sortBy([['hasta', 'asc'], ['contrato_id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Prepara en borrador el nombramiento definitivo, que rige desde el día
     * siguiente al último de la prueba.
     */
    public function preparar(ContratoServidor $contrato): MovimientoPersonal
    {
        if ($contrato->estado !== EstadoContrato::VIGENTE || ! $contrato->esDePrueba()) {
            throw new ReglaNegocioException(
                'Solo un nombramiento provisional de prueba vigente recibe el nombramiento definitivo.'
            );
        }

        $fin = $contrato->finDelPeriodoDePrueba();
        $ascenso = $contrato->esAscensoEnPrueba() ? $contrato->movimientoAscenso : null;
        $meses = $contrato->supuesto_provisional->mesesDePrueba() === 6 ? 'seis meses' : 'tres meses';

        if (! $fin) {
            throw new ReglaNegocioException(
                'No se encuentra cuándo empezó este período de prueba. Revíselo con la Dirección de Talento Humano.'
            );
        }

        if ($fin->gte(now()->startOfDay())) {
            throw new ReglaNegocioException(
                'El período de prueba termina el '.$fin->format('d/m/Y').": el nombramiento definitivo se "
                    ."prepara cuando se cumplan los {$meses} (LOSEP Art. 17 b.5)."
            );
        }

        if ($previo = $this->definitivoDe($contrato)) {
            throw new ReglaNegocioException(
                $previo->codigo_registro
                    ? "Este período de prueba ya tiene su nombramiento definitivo ({$previo->codigo_registro})."
                    : 'Este período de prueba ya tiene un nombramiento definitivo en trámite: revíselo en la bandeja.'
            );
        }

        // Quien no superó la prueba del ascenso vuelve a su puesto: no se le
        // prepara el definitivo encima.
        if ($ascenso && ($reintegro = $ascenso->reintegro()->first())) {
            throw new ReglaNegocioException(
                'Este ascenso tiene un reintegro'.($reintegro->codigo_registro ? " ({$reintegro->codigo_registro})" : ' en trámite')
                    .': el servidor vuelve a su puesto anterior, así que no recibe el nombramiento definitivo.'
            );
        }

        $inicio = $contrato->inicioDelPeriodoDePrueba();

        return $this->movimientos->registrar($contrato->servidor_id, [
            'tipo_movimiento'             => TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO->value,
            'descripcion'                 => 'Nombramiento definitivo al concluir el período de prueba de '.$meses
                .($ascenso ? ' del ascenso' : '').', del '.$inicio->format('d/m/Y').' al '.$fin->format('d/m/Y')
                .', conforme al Art. 17 literal b.5 de la Ley Orgánica de Servicio Público.',
            'fecha_efectiva'              => $fin->copy()->addDay()->toDateString(),
            // Mismo puesto, ahora con nombramiento permanente.
            'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
            'puesto_destino_id'           => $contrato->puesto_id,
            'unidad_destino_id'           => $contrato->unidad_administrativa_id,
            // El acto que lo puso en prueba: el ingreso o el ascenso.
            'movimiento_relacionado_id'   => $ascenso?->id ?? $contrato->movimiento_origen_id,
        ]);
    }

    /**
     * Los períodos de prueba que concluyeron sin nombramiento definitivo: se
     * les prepara en borrador. Lo llama el comando diario. Solo los vínculos
     * con supuesto de prueba, que existe desde la fase 3.2: los provisionales
     * de antes no lo tienen y no entran. Tampoco el ascendido que ya tiene su
     * reintegro.
     *
     * @return array{preparados: list<array{contrato_id: int, movimiento_id: int, servidor_id: int}>, omitidos: list<array{contrato_id: int, servidor_id: int, motivo: string}>}
     */
    public function prepararLosConcluidos(string $fecha): array
    {
        $contratos = $this->enPrueba()
            ->with('movimientoAscenso')
            ->get()
            ->filter(fn (ContratoServidor $c) => $c->finDelPeriodoDePrueba()?->lt(Carbon::parse($fecha)->startOfDay())
                && ! $this->definitivoDe($c)
                && ! $c->movimientoAscenso?->reintegro()->exists());

        $resultado = ['preparados' => [], 'omitidos' => []];

        foreach ($contratos as $contrato) {
            try {
                $definitivo = $this->preparar($contrato);

                $resultado['preparados'][] = [
                    'contrato_id'   => $contrato->id,
                    'movimiento_id' => $definitivo->id,
                    'servidor_id'   => $contrato->servidor_id,
                ];
            } catch (ReglaNegocioException $e) {
                $resultado['omitidos'][] = [
                    'contrato_id' => $contrato->id,
                    'servidor_id' => $contrato->servidor_id,
                    'motivo'      => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    /** Los vínculos vigentes en prueba: de quien ingresó por concurso y de quien ascendió. */
    private function enPrueba(): Builder
    {
        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::PROVISIONAL->value)
            ->whereIn('supuesto_provisional', array_map(
                fn (SupuestoProvisional $s) => $s->value,
                array_filter(SupuestoProvisional::cases(), fn (SupuestoProvisional $s) => $s->esDePrueba()),
            ))
            ->orderBy('fecha_inicio')
            ->orderBy('id');
    }

    /**
     * El nombramiento definitivo no anulado de este período de prueba, si lo
     * hay. Cuenta desde que empezó la prueba, no el vínculo: el ascendido pudo
     * tener ya uno, el de cuando ingresó.
     */
    private function definitivoDe(ContratoServidor $contrato): ?MovimientoPersonal
    {
        $inicio = $contrato->inicioDelPeriodoDePrueba() ?? $contrato->fecha_inicio;

        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', '>', $inicio->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    /** @return array{id: int, estado: string, codigo_registro: ?string}|null */
    private function resumen(?MovimientoPersonal $movimiento): ?array
    {
        return $movimiento ? [
            'id'              => $movimiento->id,
            'estado'          => $movimiento->estado->value,
            'codigo_registro' => $movimiento->codigo_registro,
        ] : null;
    }
}
