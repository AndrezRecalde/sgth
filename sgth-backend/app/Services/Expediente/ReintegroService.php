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
use Carbon\Carbon;

/**
 * El reintegro (LOSEP 32; diseño de Acciones de Personal, 4.2; TH 17 y 18;
 * fase 2.4): el acto que cierra una comisión de servicios o una licencia sin
 * remuneración cuando el servidor vuelve. Hasta aquí no existía, y una ausencia
 * que terminaba antes solo se podía anular, aunque ya hubiera surtido efecto.
 *
 * Nace de la ausencia que cierra —el botón «Reintegrar» de «Ausencias y
 * reemplazos», o el comando diario cuando la ausencia llega a su fin— y no del
 * formulario genérico, porque sin ella no sabe qué cerrar. La ausencia no se
 * toca: termina el día anterior a que rija su reintegro
 * (`MovimientoPersonal::finEfectivo()`).
 *
 * Al surtir efecto, si alguien cubría la ausencia, se le prepara en borrador la
 * cesación [TH 18]: el titular recupera su puesto, y la plaza no admite dos.
 */
class ReintegroService
{
    /** Hasta cuántos días atrás mira el comando diario. */
    private const DIAS_HACIA_ATRAS = 30;

    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
    ) {
    }

    /**
     * @param  array{fecha_regreso: string, descripcion: string, observacion?: ?string}  $datos
     */
    public function preparar(MovimientoPersonal $ausencia, array $datos): MovimientoPersonal
    {
        if (! $ausencia->esAusenciaTemporal()) {
            throw new ReglaNegocioException(
                'Solo se reintegra a quien está en comisión de servicios o con licencia sin remuneración.'
            );
        }

        if (! in_array($ausencia->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
            throw new ReglaNegocioException(
                'La ausencia tiene que estar registrada para preparar su reintegro.'
            );
        }

        $previo = $ausencia->reintegro()->first();

        if ($previo) {
            throw new ReglaNegocioException(
                $previo->codigo_registro
                    ? "Esta ausencia ya tiene su reintegro ({$previo->codigo_registro})."
                    : 'Esta ausencia ya tiene un reintegro en trámite: revíselo en la bandeja.'
            );
        }

        $regreso = Carbon::parse($datos['fecha_regreso'])->toDateString();

        $this->validarFechaDeRegreso($ausencia, $regreso);

        // Por registrar(), como cualquier acto: nace en borrador, no se tramita
        // sobre uno mismo y pasa por la elegibilidad del nombramiento.
        return $this->movimientos->registrar($ausencia->servidor_id, [
            'tipo_movimiento'           => TipoMovimientoPersonal::REINTEGRO->value,
            'descripcion'               => $datos['descripcion'],
            'observacion'               => $datos['observacion'] ?? null,
            'fecha_efectiva'            => $regreso,
            'movimiento_relacionado_id' => $ausencia->id,
        ]);
    }

    /**
     * El regreso cae después del inicio —volver el mismo día es no haberse ido,
     * y eso se anula— y a más tardar el día siguiente al fin pactado.
     */
    private function validarFechaDeRegreso(MovimientoPersonal $ausencia, string $regreso): void
    {
        $inicio = $ausencia->fecha_inicio;

        if ($inicio && $regreso <= $inicio->toDateString()) {
            throw new ReglaNegocioException(
                "El regreso tiene que ser posterior al inicio de la ausencia ({$inicio->format('d/m/Y')})."
            );
        }

        $fin = $ausencia->fecha_fin;

        if ($fin && $regreso > $fin->copy()->addDay()->toDateString()) {
            throw new ReglaNegocioException(
                "La ausencia termina el {$fin->format('d/m/Y')}: el regreso es a más tardar el "
                    .$fin->copy()->addDay()->format('d/m/Y').'.'
            );
        }
    }

    /**
     * Fin natural: a las ausencias que llegaron a su fecha de fin sin reintegro
     * se les prepara uno en borrador, con regreso al día siguiente, para que
     * Talento Humano lo revise y lo registre. Lo llama el comando diario.
     *
     * Solo mira las que terminaron en los últimos 30 días: las comisiones y
     * licencias de antes de que el reintegro existiera terminaron sin él, y no
     * se les prepara uno ahora. Tampoco a quien ya no tiene vínculo: no tiene a
     * qué volver.
     *
     * @return array{preparados: list<array{ausencia_id: int, movimiento_id: int, servidor_id: int}>, omitidas: list<array{ausencia_id: int, servidor_id: int, motivo: string}>}
     */
    public function prepararLosDeAusenciasTerminadas(string $fecha): array
    {
        $desde = Carbon::parse($fecha)->subDays(self::DIAS_HACIA_ATRAS)->toDateString();

        // Por query(): el modelo tiene también un esAusenciaTemporal() de
        // instancia, y la llamada estática caería en ese.
        $terminadas = MovimientoPersonal::query()->esAusenciaTemporal()
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $fecha)
            ->whereDate('fecha_fin', '>=', $desde)
            ->whereDoesntHave('reintegro')
            ->whereHas('servidor.contratos', fn ($q) => $q->where('estado', EstadoContrato::VIGENTE->value))
            ->orderBy('fecha_fin')
            ->orderBy('id')
            ->get();

        $resultado = ['preparados' => [], 'omitidas' => []];

        foreach ($terminadas as $ausencia) {
            $fin = $ausencia->fecha_fin;

            try {
                $reintegro = $this->preparar($ausencia, [
                    'fecha_regreso' => $fin->copy()->addDay()->toDateString(),
                    'descripcion'   => "Reintegro al término de la {$ausencia->etiquetaAusencia()} "
                        ."({$ausencia->codigo_registro}), que terminó el {$fin->format('d/m/Y')}. "
                        .'Preparado automáticamente para revisión de Talento Humano.',
                ]);

                $resultado['preparados'][] = [
                    'ausencia_id'   => $ausencia->id,
                    'movimiento_id' => $reintegro->id,
                    'servidor_id'   => $ausencia->servidor_id,
                ];
            } catch (ReglaNegocioException $e) {
                // Una que no se pueda —el nombramiento cambió, por ejemplo— no
                // detiene las demás: sale en la lista con su motivo.
                $resultado['omitidas'][] = [
                    'ausencia_id' => $ausencia->id,
                    'servidor_id' => $ausencia->servidor_id,
                    'motivo'      => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Lo que arrastra el reintegro al surtir efecto [TH 18]: quien cubría la
     * ausencia deja la plaza el día anterior al regreso. Se le prepara la
     * cesación en borrador —«Terminación por cumplimiento del plazo» si es
     * ocasional, «Contrato finalizado» si es profesional—, enlazada al
     * reintegro, y Talento Humano la registra.
     *
     * Si ya tiene una cesación en curso —la del vencimiento de su contrato,
     * cuando el titular vuelve en la fecha pactada—, no se prepara otra.
     *
     * @return list<MovimientoPersonal>
     */
    public function prepararSalidaDelReemplazo(MovimientoPersonal $reintegro): array
    {
        $ausencia = $reintegro->movimientoRelacionado;

        if (! $ausencia || ! $reintegro->fecha_efectiva) {
            return [];
        }

        $contratos = ContratoServidor::where('cubre_movimiento_id', $ausencia->id)
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->orderBy('id')
            ->get();

        $titular = $ausencia->servidor;
        $nombre = trim(($titular?->nombre ?? '').' '.($titular?->apellido ?? ''));
        $ultimoDia = $reintegro->fecha_efectiva->copy()->subDay()->toDateString();

        $salidas = [];

        foreach ($contratos as $contrato) {
            if ($this->tieneCesacionEnCurso($contrato)) {
                continue;
            }

            $ocasional = $contrato->tipo_nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES;
            $causal = $ocasional
                ? SubtipoMovimientoPersonal::FIN_DEL_PLAZO
                : SubtipoMovimientoPersonal::CONTRATO_FINALIZADO;

            $salidas[] = $this->movimientos->registrar($contrato->servidor_id, [
                'tipo_movimiento'           => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                'subtipo_movimiento'        => $causal->value,
                'descripcion'               => "Terminación del contrato de reemplazo: {$nombre}, a quien cubría, "
                    .'se reintegra el '.$reintegro->fecha_efectiva->format('d/m/Y')
                    .($reintegro->codigo_registro ? " ({$reintegro->codigo_registro})" : '').'. '
                    .'Preparada automáticamente para revisión de Talento Humano.',
                // La observación no se imprime: es para quien revisa.
                'observacion'               => $ocasional && ProteccionMaternidad::consta($contrato->servidor_id)
                    ? ProteccionMaternidad::aviso()
                    : null,
                'fecha_efectiva'            => $ultimoDia,
                'movimiento_relacionado_id' => $reintegro->id,
            ]);
        }

        return $salidas;
    }

    private function tieneCesacionEnCurso(ContratoServidor $contrato): bool
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', '>=', $contrato->fecha_inicio->toDateString())
            ->exists();
    }
}
