<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\EstadoSubrogacion;
use App\Enums\EstadoVacacion;
use App\Enums\MotivoSubrogacion;
use App\Enums\MotivoVacacion;
use App\Enums\RolPuesto;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoActoDeOrigen;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSubrogacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
 * Y desde la 4.1d sus vacaciones: **siguen siendo de Asistencia y sin acción de
 * personal** [TH N11], pero **se las concede el Consejo por resolución, dentro
 * de los mismos 60 días** [TH D4, 2026-11-26]. Las dos respuestas conviven: la
 * primera dice qué se escribe —una solicitud de vacaciones, no un acto—, la
 * segunda quién autoriza.
 *
 * Y el relevo (2026-10-21): quien entra a un puesto de autoridad sucede a
 * quien lo ocupa, cuyo período tiene que terminar a más tardar ese día. Las
 * autoridades que entran se ven antes de que rija su período. Desde ese día
 * todo período tiene fin: lo exige la base, y a las de la carga inicial se les
 * fijó con el período legal (2026_11_15_000000).
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
     * El Viceprefecto que designa el Consejo Provincial (COOTAD Art. 47 n;
     * TH, 2026-10-19) empieza con la resolución en lugar de la credencial, sin
     * acta de posesión, y termina con el período del Prefecto en funciones: el
     * fin lo pone el sistema. Queda con la modalidad de las autoridades, para
     * que la subrogación por ley y una nueva asunción lo encuentren.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completarInicio(Servidor $servidor, array $datos, ?SubtipoMovimientoPersonal $causal = null): array
    {
        if ($causal === SubtipoMovimientoPersonal::DESIGNACION_DEL_CONSEJO) {
            $datos = $this->completarDesignacion($datos);
        } elseif (blank($datos['credencial_cne_numero'] ?? null) || blank($datos['credencial_cne_fecha'] ?? null)) {
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
            $this->validarQueTerminaElSaliente(
                $servidor->id,
                filled($datos['puesto_destino_id'] ?? null) ? (int) $datos['puesto_destino_id'] : null,
                $inicio,
            );
        }

        $puesto = filled($datos['puesto_destino_id'] ?? null)
            ? Puesto::with('grupoOcupacional')->find($datos['puesto_destino_id'])
            : null;

        return [
            ...$datos,
            'tipo_nombramiento_propuesto'    => TipoNombramiento::ELECCION_POPULAR->value,
            // El número del vínculo es el de la credencial, o el de la
            // resolución del Consejo: no hay contrato.
            'numero_contrato'                => trim((string) ($causal === SubtipoMovimientoPersonal::DESIGNACION_DEL_CONSEJO
                ? $datos['acto_origen_numero']
                : $datos['credencial_cne_numero'])),
            'remuneracion_propuesta'         => $datos['remuneracion_propuesta'] ?? $puesto?->rmu,
            'puede_marcar'                   => false,
            'requiere_dictamen_medico'       => false,
            'supuesto_provisional_propuesto' => null,
            'acta_concurso_numero'           => null,
            'acta_concurso_fecha'            => null,
            'cubre_movimiento_id'            => null,
        ];
    }

    /**
     * La designación del Consejo: la resolución, número y fecha, como acto de
     * origen; sin credencial ni acta de posesión; y el fin del período del
     * Prefecto en funciones, que es el que completa.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function completarDesignacion(array $datos): array
    {
        if (blank($datos['acto_origen_numero'] ?? null) || blank($datos['acto_origen_fecha'] ?? null)) {
            throw new ReglaNegocioException(
                'Indique la resolución del Consejo Provincial, número y fecha: con ella empieza el período del '
                    .'Viceprefecto designado (COOTAD Art. 47 n).'
            );
        }

        $prefecto = $this->prefecto();

        if (! $prefecto) {
            throw new ReglaNegocioException(
                'No hay un Prefecto en funciones: el Viceprefecto designado termina con su período.'
            );
        }

        return [
            ...$datos,
            'acto_origen_tipo'      => TipoActoDeOrigen::RESOLUCION->value,
            'credencial_cne_numero' => null,
            'credencial_cne_fecha'  => null,
            'acta_posesion_numero'  => null,
            'fecha_fin_propuesta'   => $prefecto->fecha_fin->toDateString(),
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
            && $inicio->fecha_efectiva !== null
            && $vigente->fecha_fin->toDateString() < $inicio->fecha_efectiva->toDateString();
    }

    /**
     * Quien entra al puesto de una autoridad la sucede: el período de quien lo
     * ocupa tiene que terminar a más tardar el día en que empieza el nuevo, o
     * el sistema tendría dos Prefectos (2026-10-21). Ese día pueden coincidir,
     * porque no ocupan plaza; desde la posesión firma el entrante. Al preparar
     * el inicio y al registrarlo.
     */
    public function validarQueTerminaElSaliente(int $servidorId, ?int $puestoId, Carbon $inicio): void
    {
        foreach ($this->salientes($servidorId, $puestoId) as $saliente) {
            $fin = $this->finDelSaliente($saliente);

            if ($fin->toDateString() > $inicio->toDateString()) {
                throw new ReglaNegocioException(
                    'El período de '.$this->nombreDe($saliente->servidor).' termina el '.$fin->format('d/m/Y')
                        .': el de quien lo sucede empieza ese día o después. Si su período termina antes, '
                        .'corríjalo en «Autoridades electas».'
                );
            }
        }
    }

    /**
     * Las autoridades en funciones en ese puesto, salvo la que entra: la
     * reelegida sigue a su propio período (validarQueCabeElPeriodo).
     *
     * @return Collection<int, ContratoServidor>
     */
    private function salientes(int $servidorId, ?int $puestoId): Collection
    {
        if (! $puestoId) {
            return collect();
        }

        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->where('puesto_id', $puestoId)
            ->where('servidor_id', '!=', $servidorId)
            ->with('servidor:id,nombre,apellido')
            ->orderBy('id')
            ->get();
    }

    /**
     * El último día de la autoridad que sale: el de su cesación registrada, si
     * renunció o la removieron, o el fin de su período.
     */
    private function finDelSaliente(ContratoServidor $contrato): Carbon
    {
        $cese = $this->ceseEnCurso($contrato);

        return $cese?->pendienteDeVigencia() && $cese->fecha_efectiva
            ? $cese->fecha_efectiva->copy()
            : $contrato->fecha_fin->copy();
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
     * Corrige el fin del período de la autoridad que entró sin su acto de
     * inicio: la que ya estaba cuando el sistema empezó a contarlo, o la de la
     * carga inicial. El sistema se lo fijó con el período legal (2026-10-21), y
     * Talento Humano lo ajusta a la credencial. Queda en la bitácora del
     * vínculo. El de un período que empezó con su acto no se toca aquí: lo dice
     * la credencial, y se corrige anulando.
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
        $this->validarDuracion($this->inicioDelPeriodoEnCurso($contrato->fecha_inicio->copy()), $fin);

        // Si ya se registró quien la sucede, termina a más tardar el día en
        // que ese empieza (2026-10-21).
        $sucesor = $this->sucesorDe($contrato);

        if ($sucesor?->fecha_efectiva && $fin->toDateString() > $sucesor->fecha_efectiva->toDateString()) {
            throw new ReglaNegocioException(
                $this->nombreDe($sucesor->servidor).' empieza su período en este puesto el '
                    .$sucesor->fecha_efectiva->format('d/m/Y').': el de esta autoridad termina a más tardar ese día.'
            );
        }

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
     * El inicio del período de cuatro años que corre hoy. El de la carga
     * inicial puede ser de uno anterior —la reelegida cargada con el primero—,
     * y contra él no cabría el fin del período en curso.
     */
    private function inicioDelPeriodoEnCurso(Carbon $inicio): Carbon
    {
        $hoy = now()->startOfDay();

        while ($inicio->copy()->addYearsNoOverflow(self::AÑOS_DEL_PERIODO)->lte($hoy)) {
            $inicio = $inicio->addYearsNoOverflow(self::AÑOS_DEL_PERIODO);
        }

        return $inicio;
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
        $inicios = $this->iniciosPorVenir();

        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->with(['servidor:id,nombre,apellido,cedula', 'puesto.cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'movimientoOrigen'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->map(function (ContratoServidor $contrato) use ($hoy, $inicios) {
                $fin = $contrato->fecha_fin;
                $inicio = $contrato->movimientoOrigen?->tipo_movimiento === TipoMovimientoPersonal::INICIO_PERIODO
                    ? $contrato->movimientoOrigen
                    : null;
                $cese = $this->ceseEnCurso($contrato);
                $siguiente = $this->siguientePeriodo($contrato);
                $sucesor = $this->sucesorDe($contrato, $inicios);
                $ausencia = $this->ausenciaEnCurso($contrato);
                $subrogacion = $this->subrogacionEnCurso($contrato);
                $asuncion = $this->asuncionEnCurso($contrato);

                return [
                    'contrato_id'     => $contrato->id,
                    'servidor'        => [
                        'id'     => $contrato->servidor?->id,
                        'nombre' => trim(($contrato->servidor?->apellido ?? '').' '.($contrato->servidor?->nombre ?? '')),
                        'cedula' => $contrato->servidor?->cedula,
                    ],
                    // El puesto, no solo su nombre: la reelección abre el
                    // formulario con la misma dignidad ya elegida (fase 4.1).
                    'puesto_id'       => $contrato->puesto_id,
                    'dignidad'        => $contrato->puesto?->cargo?->nombre,
                    'unidad'          => $contrato->unidadAdministrativa?->nombre,
                    'desde'           => $contrato->fecha_inicio?->toDateString(),
                    'hasta'           => $fin->toDateString(),
                    'dias_restantes'  => (int) $hoy->diffInDays($fin, false),
                    'credencial'      => $inicio?->credencial_cne_numero,
                    // Si el Consejo lo designó, su resolución (2026-10-20).
                    'designacion'     => $inicio?->subtipoEfectivo() === SubtipoMovimientoPersonal::DESIGNACION_DEL_CONSEJO
                        ? $inicio->acto_origen_numero
                        : null,
                    'inicio'          => $this->resumen($inicio),
                    // El fin lo corrige Talento Humano si no lo dice un acto.
                    'puede_fijar_fin' => ! $inicio && ! $cese,
                    'cese'            => $cese ? [...$this->resumen($cese), 'causal' => $cese->subtipoEfectivo()?->etiqueta()] : null,
                    'siguiente'       => $this->resumen($siguiente),
                    // Quien entra a su puesto cuando termina (2026-10-21).
                    'sucesor'         => $sucesor
                        ? [...$this->resumen($sucesor), 'nombre' => $this->nombreDe($sucesor->servidor)]
                        : null,
                    // La licencia del Consejo en curso o por venir (4.1b).
                    'ausencia'        => $ausencia ? [
                        ...$this->resumen($ausencia),
                        'desde' => $ausencia->fecha_inicio?->toDateString(),
                        'hasta' => $ausencia->finEfectivo()?->toDateString(),
                    ] : null,
                    // Quién lo subroga, o a quién subroga (4.1b).
                    'subrogacion'     => $subrogacion,
                    // La dignidad que va a asumir, cuando el Prefecto se fue
                    // para siempre y esta asunción todavía no rige (4.1b).
                    'asuncion'        => $asuncion ? [
                        ...$this->resumen($asuncion),
                        'dignidad' => Puesto::with('cargo')->find($asuncion->puesto_destino_id)?->cargo?->nombre,
                    ] : null,
                    'es_prefecto'     => $this->prefecto()?->id === $contrato->id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Las dignidades de elección popular: la Prefectura y la Viceprefectura, y
     * quién las ocupa hoy (fase 4.1).
     *
     * Las necesita el formulario del inicio del período, que no ofrece ningún
     * otro puesto — es la misma regla que el servicio ya impone al registrar.
     * Se reconoce una dignidad por el rol del puesto o, si no lo tiene, por el
     * de su grado: `GrupoOcupacionalSeeder` marca NJS-10 (Prefecto/a) y NJS-7
     * (Viceprefecto/a), y es ahí donde el dato existe seguro.
     *
     * Trae también al ocupante y hasta cuándo, porque el período que entra no
     * puede empezar antes de que termine el suyo y el formulario lo dice antes
     * de dejar enviar.
     *
     * @return list<array<string, mixed>>
     */
    public function dignidades(): array
    {
        return Puesto::query()
            ->with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'grupoOcupacional'])
            ->where('activo', true)
            ->where(fn ($q) => $q
                ->where('rol_puesto', RolPuesto::DIGNATARIO->value)
                ->orWhereHas('grupoOcupacional', fn ($g) => $g->where('rol_puesto', RolPuesto::DIGNATARIO->value)))
            ->get()
            ->map(function (Puesto $puesto) {
                $ocupante = ContratoServidor::with('servidor:id,nombre,apellido')
                    ->where('puesto_id', $puesto->id)
                    ->where('estado', EstadoContrato::VIGENTE->value)
                    ->orderByDesc('fecha_inicio')
                    ->first();

                return [
                    'puesto_id'                => $puesto->id,
                    'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
                    'dignidad'                 => $puesto->cargo?->nombre,
                    'unidad'                   => $puesto->unidadAdministrativa?->nombre,
                    'rmu'                      => $puesto->grupoOcupacional?->rmu,
                    'ocupada_por'              => $ocupante ? $this->nombreDe($ocupante->servidor) : null,
                    'ocupada_hasta'            => $ocupante?->fecha_fin?->toDateString(),
                ];
            })
            ->sortBy('dignidad')
            ->values()
            ->all();
    }

    /**
     * Las autoridades que entran (2026-10-21): su período por empezar, a quién
     * suceden y, si el relevo no cuadra, por qué. Antes de que rija no tienen
     * vínculo, así que no salen en listar().
     *
     * @return list<array<string, mixed>>
     */
    public function entrantes(): array
    {
        return $this->iniciosPorVenir()
            ->map(function (MovimientoPersonal $inicio) {
                $puestoId = $inicio->puesto_destino_id ? (int) $inicio->puesto_destino_id : null;
                $saliente = $this->salientes((int) $inicio->servidor_id, $puestoId)->first();

                // Lo registrado antes de la regla, o en trámite: la misma
                // regla que lo detendría al registrarlo, como aviso.
                $aviso = null;

                if ($inicio->fecha_efectiva) {
                    try {
                        $this->validarQueTerminaElSaliente((int) $inicio->servidor_id, $puestoId, $inicio->fecha_efectiva->copy());
                    } catch (ReglaNegocioException $e) {
                        $aviso = $e->getMessage();
                    }
                }

                return [
                    'movimiento_id' => $inicio->id,
                    'servidor'      => [
                        'id'     => $inicio->servidor?->id,
                        'nombre' => $this->nombreDe($inicio->servidor),
                        'cedula' => $inicio->servidor?->cedula,
                    ],
                    'dignidad'      => $inicio->puestoDestino?->cargo?->nombre,
                    'unidad'        => $inicio->unidadDestino?->nombre,
                    'desde'         => $inicio->fecha_efectiva?->toDateString(),
                    'hasta'         => $inicio->fecha_fin_propuesta?->toDateString(),
                    'credencial'    => $inicio->credencial_cne_numero,
                    'designacion'   => $inicio->subtipoEfectivo() === SubtipoMovimientoPersonal::DESIGNACION_DEL_CONSEJO
                        ? $inicio->acto_origen_numero
                        : null,
                    'inicio'        => $this->resumen($inicio),
                    'sucede_a'      => $saliente ? [
                        'contrato_id' => $saliente->id,
                        'nombre'      => $this->nombreDe($saliente->servidor),
                        'hasta'       => $this->finDelSaliente($saliente)->toDateString(),
                    ] : null,
                    'aviso'         => $aviso,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Los inicios de período de quien todavía no está en funciones como
     * autoridad: en trámite, o registrados y esperando su fecha. El de la
     * reelegida no: se ve en su fila (siguientePeriodo).
     *
     * @return Collection<int, MovimientoPersonal>
     */
    private function iniciosPorVenir(): Collection
    {
        return MovimientoPersonal::where('tipo_movimiento', TipoMovimientoPersonal::INICIO_PERIODO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereNull('efecto_aplicado_en')
            ->whereDoesntHave('servidor.contratos', fn ($q) => $q->where('estado', EstadoContrato::VIGENTE->value)
                ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value))
            ->with(['servidor:id,nombre,apellido,cedula', 'puestoDestino.cargo:id,nombre', 'unidadDestino:id,nombre'])
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();
    }

    /** Quien entra al puesto de esta autoridad: el inicio por venir de otra persona en él. */
    private function sucesorDe(ContratoServidor $contrato, ?Collection $inicios = null): ?MovimientoPersonal
    {
        return ($inicios ?? $this->iniciosPorVenir())->first(
            fn (MovimientoPersonal $m) => (int) $m->puesto_destino_id === (int) $contrato->puesto_id
                && (int) $m->servidor_id !== (int) $contrato->servidor_id
        );
    }

    private function nombreDe(?Servidor $servidor): string
    {
        return trim(($servidor?->apellido ?? '').' '.($servidor?->nombre ?? ''));
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

        $this->exigirTopeDelConsejo(
            $servidor, $inicio, $fin, 'licencia del Consejo',
            exceptoLicencia: $excepto,
        );
    }

    /**
     * Las vacaciones de una autoridad electa (fase 4.1d).
     *
     * **Se las concede el Consejo Provincial por resolución, dentro de los 60
     * días** del COOTAD Art. 47 s [TH D4]. No hay jefe inmediato que las
     * autorice ni papel que baje a ventanilla: la resolución es las dos cosas,
     * así que la solicitud nace y se registra de una vez.
     *
     * Siguen siendo una solicitud de vacaciones y no un acto de autoridad [TH
     * N11]: descuentan del mismo saldo, con los mismos períodos, el mismo
     * conteo de días calendario y los mismos cruces que las de cualquiera.
     * Que las conceda el Consejo no multiplica el derecho.
     *
     * @param  array{fecha_inicio: string, fecha_fin: string, resolucion_numero: string, observacion?: ?string}  $datos
     */
    public function registrarVacaciones(Servidor $servidor, array $datos, User $registra): Vacacion
    {
        if (! $this->esAutoridadElecta($servidor)) {
            throw new ReglaNegocioException(
                'Esta pantalla registra las vacaciones que el Consejo concede a una autoridad electa en funciones. '
                    .'Las de un servidor se registran en «Vacaciones».'
            );
        }

        if (blank($datos['resolucion_numero'] ?? null)) {
            throw new ReglaNegocioException(
                'Indique la resolución del Consejo Provincial que le concede las vacaciones.'
            );
        }

        if (blank($datos['fecha_inicio'] ?? null) || blank($datos['fecha_fin'] ?? null)) {
            throw new ReglaNegocioException('Indique desde y hasta cuándo son las vacaciones.');
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'])->startOfDay();

        if ($fin->lt($inicio)) {
            throw new ReglaNegocioException('Las vacaciones terminan después de empezar.');
        }

        $this->exigirTopeDelConsejo($servidor, $inicio, $fin, 'vacaciones');

        return DB::transaction(function () use ($servidor, $datos, $inicio, $fin, $registra) {
            $vacaciones = app(VacacionService::class);

            // El segundo tope —sus días acumulados— y los cruces los comprueba
            // el módulo de Vacaciones, que es el que los sabe. `por_resolucion`
            // es lo que le dice que esta sí pasa: la vía ordinaria le está
            // cerrada a una autoridad justamente para que no se salte al
            // Consejo (fase 4.1d).
            $vacacion = $vacaciones->solicitar([
                'motivo'             => MotivoVacacion::VACACIONES_ANUALES->value,
                'fecha_inicio'       => $inicio->toDateString(),
                'fecha_fin'          => $fin->toDateString(),
                'observacion'        => $datos['observacion'] ?? null,
                'resolucion_numero'  => trim((string) $datos['resolucion_numero']),
                'creado_por'         => $registra->id,
                'por_resolucion'     => true,
            ], $servidor->id);

            // La resolución es la autorización y el papel firmado a la vez: no
            // hay nada que esperar en ventanilla, así que los días salen ya.
            return $vacaciones->registrar($vacacion->id, $registra);
        });
    }

    /** ¿Tiene vínculo vigente de elección popular? */
    public function esAutoridadElecta(Servidor $servidor): bool
    {
        return $servidor->contratoVigente?->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR;
    }

    /**
     * El tope del COOTAD Art. 47 s: **60 días al año**, y es uno solo para todo
     * lo que concede el Consejo. Desde la fase 4.1d suma las licencias **y las
     * vacaciones**: son dos permisos de la misma mano, y contarlos por separado
     * dejaba conceder 120.
     *
     * Se cuenta año por año porque un permiso puede cruzar el 31 de diciembre,
     * y el tope es anual.
     */
    private function exigirTopeDelConsejo(
        Servidor $servidor,
        Carbon $inicio,
        Carbon $fin,
        string $queEs,
        ?int $exceptoLicencia = null,
        ?int $exceptoVacacion = null,
    ): void {
        $licencias = MovimientoPersonal::where('servidor_id', $servidor->id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::LICENCIA_CONSEJO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->when($exceptoLicencia, fn ($q) => $q->where('id', '!=', $exceptoLicencia))
            ->get();

        // Las que concedió el Consejo son las que llevan su resolución. Las
        // que esta misma autoridad pudiera tener registradas por la vía
        // ordinaria —de antes de que se le cerrara— no cuentan: nadie del
        // Consejo las concedió, y sumarlas bloquearía las que sí corresponden.
        $vacaciones = Vacacion::where('servidor_id', $servidor->id)
            ->whereNotNull('resolucion_numero')
            ->where('estado', '!=', EstadoVacacion::ANULADA->value)
            ->when($exceptoVacacion, fn ($q) => $q->where('id', '!=', $exceptoVacacion))
            ->get();

        for ($anio = $inicio->year; $anio <= $fin->year; $anio++) {
            $dias = $this->diasEnElAnio($inicio, $fin, $anio)
                + $licencias->sum(fn (MovimientoPersonal $l) => $l->fecha_inicio && $l->finEfectivo()
                    ? $this->diasEnElAnio($l->fecha_inicio->copy()->startOfDay(), $l->finEfectivo()->copy()->startOfDay(), $anio)
                    : 0)
                + $vacaciones->sum(fn (Vacacion $v) => $v->fecha_inicio && $v->fecha_fin
                    ? $this->diasEnElAnio($v->fecha_inicio->copy()->startOfDay(), $v->fecha_fin->copy()->startOfDay(), $anio)
                    : 0);

            if ($dias > self::DIAS_DE_LICENCIA_AL_ANIO) {
                throw new ReglaNegocioException(
                    "En {$anio} la autoridad sumaría {$dias} días concedidos por el Consejo entre licencias y "
                        ."vacaciones, y estas {$queEs} no caben: el tope es ".self::DIAS_DE_LICENCIA_AL_ANIO
                        .' al año (COOTAD Art. 47 s). Si el Consejo prorrogó una licencia por enfermedad '
                        .'catastrófica o calamidad doméstica, indique la causa al registrarla.'
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
     * nominadora. El día del cambio están el saliente y el entrante: desde la
     * posesión manda el que entra, el de inicio más reciente (2026-10-21).
     */
    public function prefecto(): ?ContratoServidor
    {
        return ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->whereHas('puesto', fn ($p) => $p->where('es_jefe', true)
                ->whereHas('unidadAdministrativa', fn ($u) => $u->where('es_maxima_autoridad', true)))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * El vínculo vigente del Viceprefecto: la otra autoridad electa en
     * funciones. Si no hay exactamente una, no se adivina. El día del cambio
     * no cuentan los salientes: el del puesto del Prefecto queda fuera, y en
     * cada puesto cuenta el que entra (2026-10-21).
     */
    public function viceprefecto(): ?ContratoServidor
    {
        $prefecto = $this->prefecto();

        $otras = ContratoServidor::where('estado', EstadoContrato::VIGENTE->value)
            ->where('tipo_nombramiento', TipoNombramiento::ELECCION_POPULAR->value)
            ->when($prefecto, fn ($q) => $q->where('id', '!=', $prefecto->id)
                ->where(fn ($p) => $p->whereNull('puesto_id')->orWhere('puesto_id', '!=', $prefecto->puesto_id)))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (ContratoServidor $c) => $c->puesto_id ?? 'sin-puesto-'.$c->id);

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
                // El documento del cese es el de la asunción [TH, 2026-11-26]:
                // la renuncia aceptada, la partida de defunción, la resolución
                // de revocatoria o de remoción. La asunción no la decide
                // nadie, la manda el Art. 51; sin heredarlo se quedaba en
                // borrador para siempre, porque `exigirDocumentoDeOrigen()` lo
                // pide al suscribir y ninguna pantalla lo dejaba escribir
                // (hallazgo H15). Sigue siendo editable en el modal de
                // suscripción, por si el Consejo emite una resolución aparte.
                'resolucion_numero'         => $cese->resolucion_numero,
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

    /**
     * La asunción definitiva que todavía no rige: el Viceprefecto que va a
     * tomar la Prefectura porque su titular se fue para siempre (COOTAD
     * Art. 51).
     *
     * La prepara sola el cese del Prefecto, y hasta el 2026-11-26 no se veía
     * en ningún sitio: la columna «Lo que sigue» del Viceprefecto decía «—»
     * mientras su asunción esperaba en la bandeja, así que nadie se enteraba
     * de que había un relevo a medio hacer. Es el acto que deja a la
     * institución sin autoridad nominadora mientras no se registre, así que es
     * justo el que tiene que estar a la vista.
     */
    private function asuncionEnCurso(ContratoServidor $contrato): ?MovimientoPersonal
    {
        return MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::ASUNCION_DEFINITIVA->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereNull('efecto_aplicado_en')
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
