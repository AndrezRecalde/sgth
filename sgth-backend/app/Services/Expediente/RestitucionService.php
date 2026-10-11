<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * La restitución (LOSEP 46; diseño de Acciones de Personal, 4.2; TH N15; fase
 * 2.6): vuelve al servidor a su puesto por sentencia ejecutoriada. Hasta aquí
 * no existía, y a quien volvía solo se le podía hacer un ingreso nuevo, que le
 * reiniciaba la antigüedad.
 *
 * - Deja sin efecto la cesación que cerró su último vínculo, que tiene que
 *   haber sido un nombramiento permanente. Se toma sola: no se elige.
 * - Vuelve al mismo puesto, con el mismo nombramiento; el vínculo nuevo es
 *   continuo con el que cerró la cesación (`Servidor::inicioDelServicioContinuo`).
 * - Si el puesto lo ocupa un provisional (LOSEP 17 b.1), se le prepara la
 *   remoción en borrador, que hay que registrar antes.
 */
class RestitucionService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
        private readonly ContratoServidorService $contratos,
    ) {
    }

    /**
     * Completa los datos de la restitución antes de crearla, y dice a qué
     * provisional hay que remover para que quepa.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: array<string, mixed>, 1: ?ContratoServidor}
     */
    public function completar(Servidor $servidor, array $datos): array
    {
        if ($servidor->contratoVigente) {
            throw new ReglaNegocioException(
                'El servidor tiene un vínculo vigente: la restitución es para quien cesó y vuelve por sentencia.'
            );
        }

        $cesacion = $this->cesacionQueDejaSinEfecto($servidor);
        $cerrado = ContratoServidor::where('movimiento_cierre_id', $cesacion->id)->firstOrFail();

        if ($cerrado->tipo_nombramiento !== TipoNombramiento::PERMANENTE) {
            throw new ReglaNegocioException(
                'La restitución es de un nombramiento permanente, y el último vínculo del servidor fue '
                    ."«{$cerrado->tipo_nombramiento?->etiqueta()}»."
            );
        }

        if ($cesacion->restitucion()->exists()) {
            throw new ReglaNegocioException(
                'Esa cesación ya tiene su restitución, en trámite o emitida.'
            );
        }

        $this->validarDatosJudiciales($datos, $cesacion);

        $puesto = $cerrado->puesto;

        $completos = [
            ...$datos,
            'movimiento_relacionado_id'   => $cesacion->id,
            // Vuelve al que tenía, con el mismo nombramiento: es lo que manda
            // la ley («a su puesto»), y no se elige.
            'puesto_destino_id'           => $cerrado->puesto_id,
            'unidad_destino_id'           => $cerrado->unidad_administrativa_id,
            'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
            'numero_contrato'             => $datos['numero_contrato'] ?? $cerrado->numero_contrato,
            // La de la escala de hoy, que es la que cobraría; si el puesto no
            // la tiene, la que cobraba.
            'remuneracion_propuesta'      => $datos['remuneracion_propuesta'] ?? $puesto?->rmu ?? $cerrado->remuneracion,
            'partida_presupuestaria_id'   => $datos['partida_presupuestaria_id']
                ?? $cerrado->partida_presupuestaria_id
                ?? $puesto?->partida_presupuestaria_id,
            'puede_marcar'                => $cerrado->puede_marcar,
            'cubre_movimiento_id'         => null,
        ];

        return [$completos, $this->provisionalQueOcupaLaPlaza($cerrado)];
    }

    /**
     * La que cerró su último vínculo, que tiene que estar emitida.
     */
    private function cesacionQueDejaSinEfecto(Servidor $servidor): MovimientoPersonal
    {
        $ultimo = $servidor->contratos()
            ->where('estado', '!=', EstadoContrato::CANCELADO->value)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        $cesacion = $ultimo?->movimiento_cierre_id
            ? MovimientoPersonal::find($ultimo->movimiento_cierre_id)
            : null;

        if (! $cesacion
            || ! $cesacion->cierraElVinculo()
            || ! in_array($cesacion->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)
        ) {
            throw new ReglaNegocioException(
                'No hay una cesación registrada que haya cerrado el último vínculo del servidor: '
                    .'la restitución deja sin efecto esa cesación.'
            );
        }

        return $cesacion;
    }

    /**
     * Los cuatro datos de la causa y las remuneraciones son obligatorios [TH
     * N15]. La ejecutoría va después de la sentencia y no después de hoy, y la
     * restitución rige desde la ejecutoría, después de la cesación.
     *
     * @param  array<string, mixed>  $datos
     */
    public function validarDatosJudiciales(array $datos, MovimientoPersonal $cesacion): void
    {
        $faltan = array_keys(array_filter([
            'el número de la causa judicial'          => blank($datos['causa_judicial_numero'] ?? null),
            'la unidad judicial o tribunal'           => blank($datos['unidad_judicial'] ?? null),
            'la fecha de la sentencia'                => blank($datos['fecha_sentencia'] ?? null),
            'la fecha de ejecutoría'                  => blank($datos['fecha_ejecutoria'] ?? null),
            'las remuneraciones dejadas de percibir'  => ! isset($datos['remuneraciones_dejadas_de_percibir'])
                || $datos['remuneraciones_dejadas_de_percibir'] === '',
        ]));

        if ($faltan !== []) {
            throw new ReglaNegocioException('Indique '.implode(', ', $faltan).' de la restitución.');
        }

        if ((float) $datos['remuneraciones_dejadas_de_percibir'] < 0) {
            throw new ReglaNegocioException('Las remuneraciones dejadas de percibir no pueden ser negativas.');
        }

        $sentencia  = Carbon::parse($datos['fecha_sentencia'])->toDateString();
        $ejecutoria = Carbon::parse($datos['fecha_ejecutoria'])->toDateString();
        $rige       = Carbon::parse($datos['fecha_efectiva'] ?? now())->toDateString();
        $cese       = $cesacion->fecha_efectiva?->toDateString();

        if ($ejecutoria < $sentencia) {
            throw new ReglaNegocioException('La ejecutoría no puede ser anterior a la sentencia.');
        }

        if ($ejecutoria > now()->toDateString()) {
            throw new ReglaNegocioException('La sentencia tiene que estar ejecutoriada: la fecha de ejecutoría no puede ser futura.');
        }

        if ($cese && $sentencia <= $cese) {
            throw new ReglaNegocioException(
                'La sentencia tiene que ser posterior a la cesación que deja sin efecto ('
                    .$cesacion->fecha_efectiva->format('d/m/Y').').'
            );
        }

        if ($rige < $ejecutoria) {
            throw new ReglaNegocioException('La restitución rige desde la ejecutoría de la sentencia, no antes.');
        }
    }

    /**
     * Si el puesto ya no tiene plaza para él, el provisional que la ocupa
     * (LOSEP 17 b.1). Si la ocupa un permanente, la restitución no cabe.
     */
    private function provisionalQueOcupaLaPlaza(ContratoServidor $cerrado): ?ContratoServidor
    {
        try {
            $this->contratos->validarVacante((int) $cerrado->puesto_id, TipoNombramiento::PERMANENTE->value);

            return null;
        } catch (ReglaNegocioException) {
            // Sin plaza: se busca quién la ocupa de forma provisional.
        }

        $provisional = ContratoServidor::where('puesto_id', $cerrado->puesto_id)
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::PROVISIONAL->value)
            ->queOcupanPlaza()
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if (! $provisional) {
            throw new ReglaNegocioException(
                'Su puesto no tiene plaza libre y no la ocupa ningún provisional que remover: '
                    .'libere una plaza antes de preparar la restitución.'
            );
        }

        return $provisional;
    }

    /**
     * La remoción del provisional, en borrador y enlazada a la restitución,
     * con fecha de la víspera. No se prepara si ya tiene una cesación en curso.
     */
    public function prepararRemocionDelProvisional(MovimientoPersonal $restitucion, ?ContratoServidor $provisional): ?MovimientoPersonal
    {
        if (! $provisional) {
            return null;
        }

        $enCurso = MovimientoPersonal::where('servidor_id', $provisional->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', '>=', $provisional->fecha_inicio->toDateString())
            ->exists();

        if ($enCurso) {
            return null;
        }

        $titular = $restitucion->servidor;
        $nombre = trim(($titular?->nombre ?? '').' '.($titular?->apellido ?? ''));

        return $this->movimientos->registrar($provisional->servidor_id, [
            'tipo_movimiento'           => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento'        => SubtipoMovimientoPersonal::REMOCION->value,
            'descripcion'               => "Remoción del nombramiento provisional: {$nombre}, restituido por "
                ."sentencia (causa {$restitucion->causa_judicial_numero}), vuelve a este puesto el "
                .$restitucion->fecha_efectiva->format('d/m/Y').'. Preparada automáticamente para revisión '
                .'de Talento Humano.',
            'fecha_efectiva'            => $restitucion->fecha_efectiva->copy()->subDay()->toDateString(),
            'movimiento_relacionado_id' => $restitucion->id,
        ]);
    }

    /**
     * Las remociones que preparó esta restitución, sin las anuladas.
     *
     * @return Collection<int, MovimientoPersonal>
     */
    public function remociones(MovimientoPersonal $restitucion): Collection
    {
        return MovimientoPersonal::where('movimiento_relacionado_id', $restitucion->id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->orderBy('id')
            ->get();
    }
}
