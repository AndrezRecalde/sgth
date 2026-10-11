<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El período de las autoridades electas: el Prefecto y el Viceprefecto
 * (diseño de Acciones de Personal, 9.14; TH 1–7; fase 4.1).
 *
 * Son servidores públicos —siguen en el expediente, la nómina y la declaración
 * patrimonial—, pero ninguno de sus actos nace de una autoridad nominadora: se
 * les registra con el documento de origen y sin acción de personal (el acto de
 * autoridad). El período empieza con la credencial del CNE y la posesión, y se
 * cierra solo en su fecha [TH 1]. La reelección abre el siguiente cuando
 * termina el anterior, sin cortar la antigüedad [TH 4].
 *
 * Las licencias y vacaciones que concede el Consejo, la subrogación por ley
 * del Viceprefecto y la asunción definitiva llegan en la 4.1b.
 */
class AutoridadElectaService
{
    /** El período de una autoridad seccional dura hasta cuatro años. */
    private const AÑOS_DEL_PERIODO = 4;

    /**
     * Valida el inicio del período y fija lo que no se elige: la elección
     * popular, el número del vínculo (el de la credencial), la remuneración de
     * la escala del puesto, sin marcación ni ficha médica [TH 5]. Al preparar y
     * al corregir el borrador.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completarInicio(Servidor $servidor, array $datos): array
    {
        if (blank($datos['credencial_cne_numero'] ?? null) || blank($datos['credencial_cne_fecha'] ?? null)) {
            throw new ReglaNegocioException(
                'Indique la credencial del CNE, número y fecha: con ella empieza el período de la autoridad.'
            );
        }

        $inicio = filled($datos['fecha_efectiva'] ?? null) ? Carbon::parse($datos['fecha_efectiva']) : null;
        $fin = filled($datos['fecha_fin_propuesta'] ?? null) ? Carbon::parse($datos['fecha_fin_propuesta']) : null;

        if (! $fin) {
            throw new ReglaNegocioException('Indique la fecha en que termina el período: ese día el vínculo se cierra solo.');
        }

        if ($inicio) {
            $this->validarDuracion($inicio, $fin);
            $this->validarQueCabeElPeriodo($servidor, $inicio);
        }

        $puesto = filled($datos['puesto_destino_id'] ?? null)
            ? Puesto::with('grupoOcupacional')->find($datos['puesto_destino_id'])
            : null;

        return [
            ...$datos,
            'tipo_nombramiento_propuesto'    => TipoNombramiento::ELECCION_POPULAR->value,
            // El número del vínculo es el de la credencial: no hay contrato.
            'numero_contrato'                => trim((string) $datos['credencial_cne_numero']),
            'remuneracion_propuesta'         => $datos['remuneracion_propuesta'] ?? $puesto?->rmu,
            'puede_marcar'                   => false,
            'requiere_dictamen_medico'       => false,
            'supuesto_provisional_propuesto' => null,
            'acta_concurso_numero'           => null,
            'acta_concurso_fecha'            => null,
            'cubre_movimiento_id'            => null,
        ];
    }

    /** Hasta cuatro años, y termina después de empezar. */
    private function validarDuracion(Carbon $inicio, Carbon $fin): void
    {
        if ($fin->lte($inicio)) {
            throw new ReglaNegocioException('El período termina después de empezar.');
        }

        $tope = $inicio->copy()->addYearsNoOverflow(self::AÑOS_DEL_PERIODO);

        if ($fin->gt($tope)) {
            throw new ReglaNegocioException(
                'El período de una autoridad electa dura hasta cuatro años: si empieza el '.$inicio->format('d/m/Y')
                    .', termina a más tardar el '.$tope->format('d/m/Y').'.'
            );
        }
    }

    /**
     * Quien no tiene vínculo empieza su primer período. Quien lo tiene solo
     * empieza otro si es la autoridad reelegida: su período actual tiene que
     * terminar antes de que empiece el nuevo [TH 4].
     */
    public function validarQueCabeElPeriodo(Servidor $servidor, Carbon $inicio): void
    {
        $vigente = Servidor::with('contratoVigente')->find($servidor->id)?->contratoVigente;

        if (! $vigente) {
            return;
        }

        if ($vigente->tipo_nombramiento !== TipoNombramiento::ELECCION_POPULAR) {
            throw new ReglaNegocioException(
                'El servidor mantiene un vínculo laboral vigente ('.$vigente->tipo_nombramiento->etiqueta().'): '
                    .'regístrele antes la Cesación de Funciones de su puesto actual.'
            );
        }

        if (! $vigente->fecha_fin) {
            throw new ReglaNegocioException(
                'Su período actual no tiene fecha de fin: fíjela antes en «Autoridades electas» para que el nuevo '
                    .'empiece cuando ese termine.'
            );
        }

        if ($vigente->fecha_fin->toDateString() >= $inicio->toDateString()) {
            throw new ReglaNegocioException(
                'El período actual termina el '.$vigente->fecha_fin->format('d/m/Y').': el nuevo empieza después.'
            );
        }
    }

    /**
     * ¿Admite el inicio de este período el vínculo que hoy tiene el servidor?
     * Lo pregunta el registro para dejar pasar la reelección.
     */
    public static function esReeleccion(?ContratoServidor $vigente, MovimientoPersonal $inicio): bool
    {
        return $inicio->tipo_movimiento === TipoMovimientoPersonal::INICIO_PERIODO
            && $vigente?->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR
            && $vigente->fecha_fin !== null
            && $inicio->fecha_efectiva !== null
            && $vigente->fecha_fin->toDateString() < $inicio->fecha_efectiva->toDateString();
    }

    /**
     * El cese de una autoridad lleva su documento de origen: el resultado del
     * CNE, la resolución del Consejo, la renuncia aceptada o la partida de
     * defunción. El fin del período no: lo marca la fecha.
     */
    public function exigirDocumentoDelCese(?SubtipoMovimientoPersonal $causal, ?string $documento): void
    {
        if ($causal === SubtipoMovimientoPersonal::FIN_DEL_PERIODO || filled($documento)) {
            return;
        }

        throw new ReglaNegocioException(
            'Indique el documento de origen del cese en «Número de resolución»: el resultado del CNE, la '
                .'resolución del Consejo Provincial, la renuncia aceptada o la partida de defunción.'
        );
    }

    /**
     * Los períodos que terminaron se cierran solos [TH 1]: se registra el «Fin
     * del período» con la fecha en que terminó, y el vínculo se cierra ese
     * día. Lo llama el comando diario, y el registro del período siguiente si
     * se adelanta al comando.
     *
     * @return array{cerrados: list<array{contrato_id: int, movimiento_id: int, servidor_id: int}>, omitidos: list<array{contrato_id: int, servidor_id: int, motivo: string}>}
     */
    public function cerrarPeriodosTerminados(string $fecha, ?int $servidorId = null): array
    {
        $contratos = ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $fecha)
            ->when($servidorId, fn ($q) => $q->where('servidor_id', $servidorId))
            ->orderBy('fecha_fin')
            ->orderBy('id')
            ->get()
            // Si ya hay un cese en curso —una renuncia que espera su fecha—, es
            // ese el que lo cierra.
            ->reject(fn (ContratoServidor $c) => $this->ceseEnCurso($c) !== null);

        $resultado = ['cerrados' => [], 'omitidos' => []];
        $estados = app(MovimientoPersonalStateService::class);

        foreach ($contratos as $contrato) {
            try {
                $cese = DB::transaction(function () use ($contrato, $estados) {
                    $cese = app(MovimientoPersonalService::class)->registrar($contrato->servidor_id, [
                        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                        'subtipo_movimiento' => SubtipoMovimientoPersonal::FIN_DEL_PERIODO->value,
                        'descripcion'        => 'Fin del período para el que fue elegido, del '
                            .$contrato->fecha_inicio->format('d/m/Y').' al '.$contrato->fecha_fin->format('d/m/Y')
                            .'. Registrado automáticamente en su fecha.',
                        'fecha_efectiva'     => $contrato->fecha_fin->toDateString(),
                    ]);

                    $estados->transicionar($cese, EstadoAccionPersonal::SUSCRITA);

                    return $estados->transicionar($cese->fresh(), EstadoAccionPersonal::REGISTRADA);
                });

                $resultado['cerrados'][] = [
                    'contrato_id'   => $contrato->id,
                    'movimiento_id' => $cese->id,
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

    /**
     * Fija el fin del período de una autoridad que no lo tiene: la que ya
     * estaba cuando el sistema empezó a contarlo, o la que entró por la carga
     * inicial. Queda en la bitácora del vínculo. El de un período que empezó
     * con su acto no se toca aquí: lo dice la credencial, y se corrige anulando.
     */
    public function fijarFinDelPeriodo(ContratoServidor $contrato, string $fecha): ContratoServidor
    {
        if ($contrato->estado !== EstadoContrato::VIGENTE || $contrato->tipo_nombramiento !== TipoNombramiento::ELECCION_POPULAR) {
            throw new ReglaNegocioException('Solo se fija el fin del período de una autoridad electa en funciones.');
        }

        if ($contrato->movimientoOrigen?->tipo_movimiento === TipoMovimientoPersonal::INICIO_PERIODO) {
            throw new ReglaNegocioException(
                'Este período empezó con su acto, que ya dice cuándo termina: si está mal, anule el inicio del '
                    .'período y regístrelo de nuevo.'
            );
        }

        if ($this->ceseEnCurso($contrato)) {
            throw new ReglaNegocioException('Esta autoridad tiene ya un cese en curso.');
        }

        $fin = Carbon::parse($fecha);
        $this->validarDuracion($contrato->fecha_inicio->copy(), $fin);

        $antes = $contrato->fecha_fin;

        DB::transaction(function () use ($contrato, $fin, $antes) {
            $contrato->update(['fecha_fin' => $fin->toDateString()]);

            EventoVinculo::create([
                'servidor_id'          => $contrato->servidor_id,
                'contrato_servidor_id' => $contrato->id,
                'tipo'                 => TipoEventoVinculo::FIN_DEL_PERIODO_FIJADO,
                'fecha'                => now()->toDateString(),
                'descripcion'          => 'El período termina el '.$fin->format('d/m/Y')
                    .($antes ? ' (antes decía '.$antes->format('d/m/Y').')' : '')
                    .'. Ese día el vínculo se cierra solo.',
                'registrado_por'       => auth()->id(),
            ]);
        });

        return $contrato->fresh();
    }

    /**
     * Las autoridades en funciones: su período, los días que le quedan y lo
     * que lo cierra o lo continúa.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $hoy = now()->startOfDay();

        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->with(['servidor:id,nombre,apellido,cedula', 'puesto.cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'movimientoOrigen'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->map(function (ContratoServidor $contrato) use ($hoy) {
                $fin = $contrato->fecha_fin;
                $inicio = $contrato->movimientoOrigen?->tipo_movimiento === TipoMovimientoPersonal::INICIO_PERIODO
                    ? $contrato->movimientoOrigen
                    : null;
                $cese = $this->ceseEnCurso($contrato);
                $siguiente = $this->siguientePeriodo($contrato);

                return [
                    'contrato_id'     => $contrato->id,
                    'servidor'        => [
                        'id'     => $contrato->servidor?->id,
                        'nombre' => trim(($contrato->servidor?->apellido ?? '').' '.($contrato->servidor?->nombre ?? '')),
                        'cedula' => $contrato->servidor?->cedula,
                    ],
                    'dignidad'        => $contrato->puesto?->cargo?->nombre,
                    'unidad'          => $contrato->unidadAdministrativa?->nombre,
                    'desde'           => $contrato->fecha_inicio?->toDateString(),
                    'hasta'           => $fin?->toDateString(),
                    'dias_restantes'  => $fin ? (int) $hoy->diffInDays($fin, false) : null,
                    'credencial'      => $inicio?->credencial_cne_numero,
                    'inicio'          => $this->resumen($inicio),
                    // El fin lo puede fijar Talento Humano si no lo dice un acto.
                    'puede_fijar_fin' => ! $inicio && ! $cese,
                    'cese'            => $cese ? [...$this->resumen($cese), 'causal' => $cese->subtipoEfectivo()?->etiqueta()] : null,
                    'siguiente'       => $this->resumen($siguiente),
                ];
            })
            ->values()
            ->all();
    }

    /** La cesación no anulada de este vínculo, en trámite o esperando su fecha. */
    private function ceseEnCurso(ContratoServidor $contrato): ?MovimientoPersonal
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereNull('efecto_aplicado_en')
            ->whereDate('fecha_efectiva', '>=', $contrato->fecha_inicio->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    /** El período siguiente de la autoridad reelegida, si ya se registró o se prepara. */
    private function siguientePeriodo(ContratoServidor $contrato): ?MovimientoPersonal
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::INICIO_PERIODO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereNull('efecto_aplicado_en')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array{id: int, estado: string, codigo_registro: ?string, fecha_efectiva: ?string}|null */
    private function resumen(?MovimientoPersonal $movimiento): ?array
    {
        return $movimiento ? [
            'id'              => $movimiento->id,
            'estado'          => $movimiento->estado->value,
            'codigo_registro' => $movimiento->codigo_registro,
            'fecha_efectiva'  => $movimiento->fecha_efectiva?->toDateString(),
        ] : null;
    }
}
