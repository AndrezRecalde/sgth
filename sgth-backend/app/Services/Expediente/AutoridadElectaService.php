<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\EstadoSubrogacion;
use App\Enums\MotivoSubrogacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSubrogacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
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
 * Desde la 4.1b, también sus ausencias y su reemplazo: la licencia que concede
 * el Consejo, la subrogación por ley del Viceprefecto y su asunción definitiva.
 * Las vacaciones siguen en Asistencia, como las de todos [TH N11].
 */
class AutoridadElectaService
{
    /** El período de una autoridad seccional dura hasta cuatro años. */
    private const AÑOS_DEL_PERIODO = 4;

    /** Los días de licencia del Consejo que caben en un año sin prórroga (COOTAD Art. 47 s). */
    private const DIAS_DE_LICENCIA_AL_ANIO = 60;

    /** Si la ausencia del Prefecto pasa de tantos días, lo subroga el Viceprefecto (COOTAD Art. 51). */
    private const DIAS_PARA_SUBROGAR = 3;

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
                $ausencia = $this->ausenciaEnCurso($contrato);
                $subrogacion = $this->subrogacionEnCurso($contrato);

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
                    // La licencia del Consejo en curso o por venir (4.1b).
                    'ausencia'        => $ausencia ? [
                        ...$this->resumen($ausencia),
                        'desde' => $ausencia->fecha_inicio?->toDateString(),
                        'hasta' => $ausencia->finEfectivo()?->toDateString(),
                    ] : null,
                    // Quién lo subroga, o a quién subroga (4.1b).
                    'subrogacion'     => $subrogacion,
                    'es_prefecto'     => $this->prefecto()?->id === $contrato->id,
                ];
            })
            ->values()
            ->all();
    }

    // ── Fase 4.1b: las ausencias de la autoridad y su reemplazo ─────

    /**
     * La licencia que concede el Consejo Provincial (COOTAD Art. 47 s): con o
     * sin remuneración, como diga su resolución, y hasta 60 días acumulados en
     * el año. Pasado el tope solo la admite una prórroga por enfermedad
     * catastrófica o calamidad doméstica.
     *
     * @param  array<string, mixed>  $datos
     */
    public function validarLicenciaDelConsejo(Servidor $servidor, array $datos, ?int $excepto = null): void
    {
        if (blank($datos['fecha_inicio'] ?? null) || blank($datos['fecha_fin'] ?? null)) {
            throw new ReglaNegocioException('Indique desde y hasta cuándo es la licencia que concede el Consejo.');
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'])->startOfDay();

        if ($fin->lt($inicio)) {
            throw new ReglaNegocioException('La licencia termina después de empezar.');
        }

        if (! array_key_exists('con_remuneracion', $datos) || $datos['con_remuneracion'] === null) {
            throw new ReglaNegocioException(
                'Indique si la licencia es con o sin remuneración, como lo dice la resolución del Consejo.'
            );
        }

        // Prorrogada por el Consejo: el tope no la detiene.
        if (filled($datos['prorroga_causa'] ?? null)) {
            return;
        }

        $otras = MovimientoPersonal::where('servidor_id', $servidor->id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::LICENCIA_CONSEJO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->get();

        for ($anio = $inicio->year; $anio <= $fin->year; $anio++) {
            $dias = $this->diasEnElAnio($inicio, $fin, $anio)
                + $otras->sum(fn (MovimientoPersonal $l) => $l->fecha_inicio && $l->finEfectivo()
                    ? $this->diasEnElAnio($l->fecha_inicio->copy()->startOfDay(), $l->finEfectivo()->copy()->startOfDay(), $anio)
                    : 0);

            if ($dias > self::DIAS_DE_LICENCIA_AL_ANIO) {
                throw new ReglaNegocioException(
                    "En {$anio} la autoridad sumaría {$dias} días de licencia del Consejo: el tope es "
                        .self::DIAS_DE_LICENCIA_AL_ANIO.' al año (COOTAD Art. 47 s). Si el Consejo la prorrogó por '
                        .'enfermedad catastrófica o calamidad doméstica, indique la causa.'
                );
            }
        }
    }

    /** Los días de [desde, hasta] que caen en ese año. */
    private function diasEnElAnio(Carbon $desde, Carbon $hasta, int $anio): int
    {
        $primero = Carbon::create($anio, 1, 1)->startOfDay();
        $ultimo = Carbon::create($anio, 12, 31)->startOfDay();

        $a = $desde->gt($primero) ? $desde : $primero;
        $b = $hasta->lt($ultimo) ? $hasta : $ultimo;

        return $b->lt($a) ? 0 : (int) $a->diffInDays($b) + 1;
    }

    /**
     * El vínculo vigente del Prefecto: la autoridad electa en la jefatura de la
     * unidad de máxima autoridad, que es la que firma como autoridad
     * nominadora.
     */
    public function prefecto(): ?ContratoServidor
    {
        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->whereHas('puesto', fn ($p) => $p->where('es_jefe', true)
                ->whereHas('unidadAdministrativa', fn ($u) => $u->where('es_maxima_autoridad', true)))
            ->orderBy('id')
            ->first();
    }

    /**
     * El vínculo vigente del Viceprefecto: la otra autoridad electa en
     * funciones. Si no hay exactamente una, no se adivina.
     */
    public function viceprefecto(): ?ContratoServidor
    {
        $prefecto = $this->prefecto();

        $otras = ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->when($prefecto, fn ($q) => $q->where('id', '!=', $prefecto->id))
            ->get();

        return $otras->count() === 1 ? $otras->first() : null;
    }

    /** ¿Es del Prefecto este puesto? La jefatura de la unidad de máxima autoridad. */
    private function esPuestoDelPrefecto(?int $puestoId): bool
    {
        return $puestoId !== null && Puesto::whereKey($puestoId)
            ->where('es_jefe', true)
            ->whereHas('unidadAdministrativa', fn ($u) => $u->where('es_maxima_autoridad', true))
            ->exists();
    }

    /**
     * La subrogación por ley del Viceprefecto (COOTAD Art. 51): si la ausencia
     * del Prefecto pasa de tres días, lo subroga y recibe su remuneración. Es la
     * misma fila de subrogaciones, así que mientras dure firma como autoridad
     * nominadora «(S)»; su acto es de autoridad, con número AU, y conserva el
     * dictamen presupuestario de toda subrogación porque paga la diferencia.
     *
     * @param  array{fecha_inicio: string, fecha_fin: string, motivo: string, resolucion_numero?: ?string, observacion?: ?string}  $datos
     */
    public function registrarSubrogacionPorLey(array $datos, ?MovimientoPersonal $ausencia = null): Subrogacion
    {
        $prefecto = $this->prefecto();
        $vice = $this->viceprefecto();

        if (! $prefecto) {
            throw new ReglaNegocioException(
                'No hay un Prefecto en funciones: la subrogación por ley es del Viceprefecto al Prefecto.'
            );
        }

        if (! $vice) {
            throw new ReglaNegocioException(
                'No se encuentra a un único Viceprefecto en funciones que subrogue al Prefecto.'
            );
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'])->startOfDay();
        $dias = $fin->lt($inicio) ? 0 : (int) $inicio->diffInDays($fin) + 1;

        if ($dias <= self::DIAS_PARA_SUBROGAR) {
            throw new ReglaNegocioException(
                'El Viceprefecto subroga por ley cuando la ausencia del Prefecto pasa de tres días (COOTAD Art. 51): '
                    ."esta dura {$dias}."
            );
        }

        return app(SubrogacionService::class)->registrar([
            'tipo'                      => TipoSubrogacion::SUBROGACION->value,
            'servidor_subrogante_id'    => $vice->servidor_id,
            'servidor_subrogado_id'     => $prefecto->servidor_id,
            'unidad_administrativa_id'  => $prefecto->unidad_administrativa_id,
            'puesto_subrogado_id'       => $prefecto->puesto_id,
            'fecha_inicio'              => $inicio->toDateString(),
            'fecha_fin'                 => $fin->toDateString(),
            'motivo'                    => $datos['motivo'],
            'resolucion_numero'         => $datos['resolucion_numero'] ?? null,
            'observacion'               => $datos['observacion'] ?? 'Subrogación por ley del Viceprefecto (COOTAD Art. 51).',
            'movimiento_relacionado_id' => $ausencia?->id,
            // Con sus reglas, no las del módulo general (fase 5.2).
            'por_ley'                   => true,
        ]);
    }

    /**
     * Registrada la licencia del Consejo de un Prefecto, si pasa de tres días
     * se prepara la subrogación por ley del Viceprefecto, en borrador. Si no se
     * puede —no hay Viceprefecto, ya hay una—, no detiene la licencia: Talento
     * Humano la registra desde «Autoridades electas».
     */
    public function prepararSubrogacionPorLey(MovimientoPersonal $licencia): ?Subrogacion
    {
        if ($licencia->tipo_movimiento !== TipoMovimientoPersonal::LICENCIA_CONSEJO
            || ! $this->esPuestoDelPrefecto($licencia->puesto_origen_id)
            || ! $licencia->fecha_inicio || ! $licencia->fecha_fin
            || MovimientoPersonal::where('movimiento_relacionado_id', $licencia->id)
                ->where('tipo_movimiento', TipoMovimientoPersonal::SUBROGACION->value)
                ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
                ->exists()
        ) {
            return null;
        }

        try {
            return $this->registrarSubrogacionPorLey([
                'fecha_inicio'      => $licencia->fecha_inicio->toDateString(),
                'fecha_fin'         => $licencia->fecha_fin->toDateString(),
                'motivo'            => MotivoSubrogacion::LICENCIA->value,
                'resolucion_numero' => $licencia->resolucion_numero,
                'observacion'       => 'Preparada al registrarse la licencia del Consejo'
                    .($licencia->codigo_registro ? " {$licencia->codigo_registro}" : '').'.',
            ], $licencia);
        } catch (ReglaNegocioException) {
            return null;
        }
    }

    /**
     * Registrado el cese anticipado del Prefecto —revocatoria, remoción,
     * renuncia o fallecimiento—, se prepara en borrador la asunción definitiva
     * del Viceprefecto: asume hasta terminar el período (COOTAD Art. 51). Al
     * fin del período no, porque termina también el del Viceprefecto.
     */
    public function prepararAsuncion(MovimientoPersonal $cese): ?MovimientoPersonal
    {
        $causal = $cese->subtipoEfectivo();

        if (! $causal?->cierraVinculo()
            || $causal === SubtipoMovimientoPersonal::FIN_DEL_PERIODO
            || $cese->instrumento !== \App\Enums\InstrumentoActo::ACTO_AUTORIDAD
            || ! $this->esPuestoDelPrefecto($cese->puesto_origen_id)
            || MovimientoPersonal::where('movimiento_relacionado_id', $cese->id)
                ->where('tipo_movimiento', TipoMovimientoPersonal::ASUNCION_DEFINITIVA->value)
                ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
                ->exists()
        ) {
            return null;
        }

        // El Viceprefecto: la otra autoridad en funciones, que no es quien cesa.
        $vice = ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->where('servidor_id', '!=', $cese->servidor_id)
            ->get();

        if ($vice->count() !== 1) {
            return null;
        }

        $vice = $vice->first();
        $puesto = Puesto::with('grupoOcupacional')->find($cese->puesto_origen_id);

        try {
            return app(MovimientoPersonalService::class)->registrar($vice->servidor_id, [
                'tipo_movimiento'           => TipoMovimientoPersonal::ASUNCION_DEFINITIVA->value,
                'descripcion'               => 'Asume la Prefectura hasta terminar el período, por la ausencia definitiva '
                    .'del Prefecto ('.mb_strtolower($causal->etiqueta())
                    .($cese->codigo_registro ? ", {$cese->codigo_registro}" : '').'), conforme al Art. 51 del COOTAD.',
                'fecha_efectiva'            => $cese->fecha_efectiva->copy()->addDay()->toDateString(),
                'puesto_destino_id'         => $cese->puesto_origen_id,
                'unidad_destino_id'         => $cese->unidad_origen_id,
                // La remuneración de la primera autoridad.
                'remuneracion_propuesta'    => $cese->remuneracion_origen ?? $puesto?->rmu,
                'partida_presupuestaria_id' => $cese->partida_origen_id,
                'movimiento_relacionado_id' => $cese->id,
            ]);
        } catch (ReglaNegocioException) {
            return null;
        }
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

    /** La licencia del Consejo que no ha terminado, registrada o en trámite. */
    private function ausenciaEnCurso(ContratoServidor $contrato): ?MovimientoPersonal
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::LICENCIA_CONSEJO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->first();
    }

    /**
     * La subrogación pendiente o activa en que esta autoridad es titular o
     * subrogante.
     *
     * @return array{rol: string, con: string, desde: ?string, hasta: ?string, estado: string}|null
     */
    private function subrogacionEnCurso(ContratoServidor $contrato): ?array
    {
        $subrogacion = Subrogacion::whereIn('estado', [EstadoSubrogacion::PENDIENTE->value, EstadoSubrogacion::ACTIVA->value])
            ->where(fn ($q) => $q->where('servidor_subrogado_id', $contrato->servidor_id)
                ->orWhere('servidor_subrogante_id', $contrato->servidor_id))
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->with(['subrogante:id,nombre,apellido', 'subrogado:id,nombre,apellido'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->first();

        if (! $subrogacion) {
            return null;
        }

        $esTitular = (int) $subrogacion->servidor_subrogado_id === (int) $contrato->servidor_id;
        $otro = $esTitular ? $subrogacion->subrogante : $subrogacion->subrogado;

        return [
            'rol'    => $esTitular ? 'subrogado' : 'subrogante',
            'con'    => trim(($otro?->apellido ?? '').' '.($otro?->nombre ?? '')),
            'desde'  => $subrogacion->fecha_inicio?->toDateString(),
            'hasta'  => $subrogacion->fecha_fin?->toDateString(),
            'estado' => $subrogacion->estado->value,
        ];
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
