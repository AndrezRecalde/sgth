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

/**
 * El período de prueba del provisional b.5 y su nombramiento definitivo
 * (LOSEP Art. 17 b.5; Reglamento 224–227; diseño de Acciones de Personal,
 * 4.2 y 9.2; TH N3; fase 3.2).
 *
 * Quien gana un concurso entra con nombramiento provisional de prueba. A los
 * tres meses, «superado el cual, o, en caso de no haberse practicado, se
 * otorgará el nombramiento definitivo». La evaluación del período de prueba
 * todavía no existe (diseño, 9.3), así que el sistema prepara siempre el
 * definitivo en borrador; si Talento Humano juzga que no superó la prueba,
 * anula ese borrador y registra la cesación.
 */
class PeriodoDePruebaService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
    ) {
    }

    /**
     * Quién está en prueba, hasta cuándo y cuántos días le quedan.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $hoy = now()->startOfDay();

        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::PROVISIONAL->value)
            ->where('supuesto_provisional', SupuestoProvisional::DE_PRUEBA->value)
            ->with(['servidor:id,nombre,apellido,cedula', 'puesto.cargo:id,nombre', 'unidadAdministrativa:id,nombre'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->map(function (ContratoServidor $contrato) use ($hoy) {
                $fin = $contrato->finDelPeriodoDePrueba();
                $definitivo = $this->definitivoDe($contrato);

                return [
                    'contrato_id'    => $contrato->id,
                    'servidor'       => [
                        'id'     => $contrato->servidor?->id,
                        'nombre' => trim(($contrato->servidor?->apellido ?? '').' '.($contrato->servidor?->nombre ?? '')),
                        'cedula' => $contrato->servidor?->cedula,
                    ],
                    'puesto'         => $contrato->puesto?->cargo?->nombre,
                    'unidad'         => $contrato->unidadAdministrativa?->nombre,
                    'desde'          => $contrato->fecha_inicio?->toDateString(),
                    'hasta'          => $fin?->toDateString(),
                    'dias_restantes' => $fin ? (int) $hoy->diffInDays($fin, false) : null,
                    'concluida'      => $fin !== null && $fin->lt($hoy),
                    'definitivo'     => $definitivo ? [
                        'id'              => $definitivo->id,
                        'estado'          => $definitivo->estado->value,
                        'codigo_registro' => $definitivo->codigo_registro,
                    ] : null,
                ];
            })
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

        if ($fin->gte(now()->startOfDay())) {
            throw new ReglaNegocioException(
                'El período de prueba termina el '.$fin->format('d/m/Y').': el nombramiento definitivo se '
                    .'prepara cuando se cumplan los tres meses (LOSEP Art. 17 b.5).'
            );
        }

        if ($previo = $this->definitivoDe($contrato)) {
            throw new ReglaNegocioException(
                $previo->codigo_registro
                    ? "Este período de prueba ya tiene su nombramiento definitivo ({$previo->codigo_registro})."
                    : 'Este período de prueba ya tiene un nombramiento definitivo en trámite: revíselo en la bandeja.'
            );
        }

        return $this->movimientos->registrar($contrato->servidor_id, [
            'tipo_movimiento'             => TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO->value,
            'descripcion'                 => 'Nombramiento definitivo al concluir el período de prueba de tres meses, del '
                .$contrato->fecha_inicio->format('d/m/Y').' al '.$fin->format('d/m/Y')
                .', conforme al Art. 17 literal b.5 de la Ley Orgánica de Servicio Público.',
            'fecha_efectiva'              => $fin->copy()->addDay()->toDateString(),
            // Mismo puesto, ahora con nombramiento permanente.
            'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
            'puesto_destino_id'           => $contrato->puesto_id,
            'unidad_destino_id'           => $contrato->unidad_administrativa_id,
            'movimiento_relacionado_id'   => $contrato->movimiento_origen_id,
        ]);
    }

    /**
     * Los períodos de prueba que concluyeron sin nombramiento definitivo: se
     * les prepara en borrador. Lo llama el comando diario. Solo los vínculos
     * con supuesto «de prueba», que existe desde la fase 3.2: los provisionales
     * de antes no lo tienen y no entran.
     *
     * @return array{preparados: list<array{contrato_id: int, movimiento_id: int, servidor_id: int}>, omitidos: list<array{contrato_id: int, servidor_id: int, motivo: string}>}
     */
    public function prepararLosConcluidos(string $fecha): array
    {
        $contratos = ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::PROVISIONAL->value)
            ->where('supuesto_provisional', SupuestoProvisional::DE_PRUEBA->value)
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->filter(fn (ContratoServidor $c) => $c->finDelPeriodoDePrueba()?->lt(Carbon::parse($fecha)->startOfDay())
                && ! $this->definitivoDe($c));

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

    /** El nombramiento definitivo no anulado de este vínculo, si lo hay. */
    private function definitivoDe(ContratoServidor $contrato): ?MovimientoPersonal
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', '>=', $contrato->fecha_inicio->toDateString())
            ->orderByDesc('id')
            ->first();
    }
}
