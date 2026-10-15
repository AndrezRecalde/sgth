<?php

namespace App\Services\Expediente;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\EstadoContrato;
use App\Enums\EstadoCredencial;
use App\Enums\EstadoPrestamo;
use App\Enums\PasoDesvinculacion;
use App\Enums\TipoDeclaracion;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicacion\Credencial;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\DesvinculacionPaso;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La desvinculación (diseño de Acciones de Personal, 9.9; fase 7.4a).
 *
 * Se abre sola cuando un acto cierra el vínculo y la persona no queda con
 * otro: la renuncia, la destitución, la jubilación, el fin del período de una
 * autoridad, la terminación del obrero, el fin del contrato civil. Quien pasa
 * de un vínculo a otro sin interrupción no se desvincula.
 *
 * Los pasos que otro módulo registra se leen de él: los bienes que siguen
 * asignados en el Inventario TI, el usuario que sigue activo, el certificado
 * laboral emitido después de la salida, la declaración patrimonial de fin de
 * gestión. La liquidación de vacaciones la calcula el sistema y la confirma
 * el Director; la indemnización la registra él, dentro del tope del Mandato 2,
 * y quien la cobra no reingresa mientras no la devuelva (fase 7.4b). Todo paso
 * puede no aplicar, con su motivo; lo que no le toca al contrato civil no
 * aplica desde que nace.
 *
 * Se cierra sola cuando cada paso está hecho o no aplica, y se cancela si el
 * cese se anula.
 */
class DesvinculacionService
{
    /** Lo que puede cobrar quien sale, con su tope (fase 7.4b). */
    public const CONCEPTOS_DE_INDEMNIZACION = [
        'jubilacion'        => 'Compensación por jubilación',
        'retiro_voluntario' => 'Compensación por retiro voluntario',
        'supresion'         => 'Indemnización por supresión del puesto',
        'despido'           => 'Indemnización por despido o desahucio',
        'otro'              => 'Otra',
    ];

    public function __construct(
        private readonly CustodiaServiceInterface $custodia,
        private readonly LiquidacionDeLaSalida $liquidacion,
    ) {
    }

    /** Al surtir efecto el cierre: la abre, si la persona no queda con otro vínculo. */
    public function abrirPorCierre(MovimientoPersonal $cierre): ?Desvinculacion
    {
        $contrato = ContratoServidor::where('movimiento_cierre_id', $cierre->id)->first();

        if (! $contrato) {
            return null;
        }

        $sigueVinculado = ContratoServidor::where('servidor_id', $contrato->servidor_id)
            ->whereKeyNot($contrato->id)
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->exists();

        if ($sigueVinculado) {
            return null;
        }

        return DB::transaction(function () use ($cierre, $contrato) {
            $desvinculacion = Desvinculacion::firstOrCreate(
                ['movimiento_cierre_id' => $cierre->id],
                [
                    'servidor_id'  => $contrato->servidor_id,
                    'contrato_id'  => $contrato->id,
                    'fecha_salida' => ($contrato->fecha_fin ?? $cierre->fecha_efectiva ?? now())->toDateString(),
                    'estado'       => Desvinculacion::ABIERTA,
                ],
            );

            $civil = $contrato->tipo_nombramiento === TipoNombramiento::SERVICIOS_PROFESIONALES;

            foreach (PasoDesvinculacion::cases() as $paso) {
                $noAplica = $civil && $paso->noAplicaAServiciosProfesionales();

                $desvinculacion->pasos()->firstOrCreate(['paso' => $paso->value], [
                    'estado'        => $noAplica ? DesvinculacionPaso::NO_APLICA : DesvinculacionPaso::PENDIENTE,
                    'nota'          => $noAplica ? 'Servicios Profesionales: es un contrato civil, no un servidor (TH N6).' : null,
                    'registrado_en' => $noAplica ? now() : null,
                ]);
            }

            return $desvinculacion;
        });
    }

    /** El cese se anuló: el vínculo vuelve, y la desvinculación no sigue. */
    public function cancelarPorAnulacion(MovimientoPersonal $cierre): void
    {
        Desvinculacion::where('movimiento_cierre_id', $cierre->id)
            ->where('estado', '!=', Desvinculacion::CANCELADA)
            ->update([
                'estado'             => Desvinculacion::CANCELADA,
                'cancelada_en'       => now(),
                'motivo_cancelacion' => 'Se anuló el cese '.($cierre->codigo_registro ?? "#{$cierre->id}").': el vínculo volvió.',
                'updated_at'         => now(),
            ]);
    }

    /**
     * Las desvinculaciones, por estado: las abiertas primero se ponen al día,
     * porque sus pasos los cierran otros módulos.
     *
     * @param  array{estado?: ?string, per_page?: ?int}  $filtros
     */
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        Desvinculacion::where('estado', Desvinculacion::ABIERTA)->get()
            ->each(fn (Desvinculacion $d) => $this->actualizarEstado($d));

        $estado = $filtros['estado'] ?? Desvinculacion::ABIERTA;

        return Desvinculacion::query()
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'movimientoCierre', 'contrato:id,tipo_nombramiento'])
            ->orderByDesc('fecha_salida')
            ->orderByDesc('id')
            ->paginate(min((int) ($filtros['per_page'] ?? 15), 100))
            ->through(fn (Desvinculacion $d) => $this->resumen($d));
    }

    public function detalle(Desvinculacion $desvinculacion): array
    {
        $desvinculacion = $this->actualizarEstado($desvinculacion);

        return [
            ...$this->resumen($desvinculacion),
            'motivo_cancelacion' => $desvinculacion->motivo_cancelacion,
            'pasos'              => $this->pasos($desvinculacion),
        ];
    }

    /**
     * Talento Humano decide un paso: hecho, no aplica o, para deshacerlo,
     * pendiente. En los que se leen de otro módulo, solo el «no aplica»: si
     * no, manda el dato.
     */
    public function marcar(Desvinculacion $desvinculacion, PasoDesvinculacion $paso, string $estado, ?string $nota, User $usuario): array
    {
        if ($desvinculacion->estado === Desvinculacion::CANCELADA) {
            throw new ReglaNegocioException('La desvinculación se canceló: el cese se anuló y el vínculo volvió.');
        }

        if ($paso === PasoDesvinculacion::REINGRESO) {
            throw new ReglaNegocioException('La prohibición de reingreso depende de la indemnización: se marca sola al registrarla y se levanta con su devolución.');
        }

        if ($estado === DesvinculacionPaso::HECHO && ! $paso->seMarca()) {
            throw new ReglaNegocioException($paso->loMarcaElDirector()
                ? "«{$paso->etiqueta()}» queda hecha cuando el Director la confirma con su cálculo."
                : "«{$paso->etiqueta()}» queda hecho solo, cuando lo registra su módulo. Si no corresponde, márquelo como que no aplica.");
        }

        if ($paso->loMarcaElDirector() && ! $usuario->can('registrar-accion-personal')) {
            throw new ReglaNegocioException("«{$paso->etiqueta()}» lo marca el Director de Talento Humano: es dinero que va a Financiero.");
        }

        $hecha = match ($paso) {
            PasoDesvinculacion::VACACIONES    => $desvinculacion->vacaciones_confirmada_en !== null,
            PasoDesvinculacion::INDEMNIZACION => $desvinculacion->indemnizacion_registrada_en !== null,
            default                           => false,
        };

        if ($hecha && $estado === DesvinculacionPaso::NO_APLICA) {
            throw new ReglaNegocioException("«{$paso->etiqueta()}» ya se registró: deshágala primero si no corresponde.");
        }

        // Volver a pendiente deshace lo confirmado: si el mes ya se entregó a
        // Financiero, la diferencia le llega como ajuste (fase 7.3a).
        if ($hecha && $estado === DesvinculacionPaso::PENDIENTE) {
            $desvinculacion->update($paso === PasoDesvinculacion::VACACIONES ? [
                'vacaciones_dias' => null, 'vacaciones_remuneracion' => null, 'vacaciones_monto' => null,
                'vacaciones_detalle' => null, 'vacaciones_confirmada_por' => null, 'vacaciones_confirmada_en' => null,
            ] : [
                'indemnizacion_concepto' => null, 'indemnizacion_monto' => null, 'indemnizacion_resolucion' => null,
                'indemnizacion_tope' => null, 'indemnizacion_registrada_por' => null, 'indemnizacion_registrada_en' => null,
                'indemnizacion_devuelta_en' => null, 'indemnizacion_devolucion_documento' => null,
                'indemnizacion_devolucion_registrada_por' => null,
            ]);
        }

        if ($estado !== DesvinculacionPaso::PENDIENTE && mb_strlen(trim((string) $nota)) < 5) {
            throw new ReglaNegocioException($estado === DesvinculacionPaso::NO_APLICA
                ? 'Diga por qué no aplica.'
                : 'Anote cómo se hizo: el documento, la fecha o el monto.');
        }

        DesvinculacionPaso::updateOrCreate(
            ['desvinculacion_id' => $desvinculacion->id, 'paso' => $paso->value],
            [
                'estado'         => $estado,
                'nota'           => $estado === DesvinculacionPaso::PENDIENTE ? null : trim((string) $nota),
                'registrado_por' => $usuario->id,
                'registrado_en'  => now(),
            ],
        );

        return $this->detalle($desvinculacion->fresh());
    }

    /**
     * El Director confirma la liquidación de vacaciones: queda congelada como
     * se calculó, y llega a Financiero en las novedades del mes de la salida.
     */
    public function confirmarLiquidacion(Desvinculacion $desvinculacion, User $usuario): array
    {
        $this->exigirDirector($usuario, 'La liquidación la confirma el Director de Talento Humano: es dinero que va a Financiero.');
        $this->exigirAbierta($desvinculacion);

        if ($desvinculacion->vacaciones_confirmada_en !== null) {
            throw new ReglaNegocioException('La liquidación ya se confirmó.');
        }

        $calculo = $this->liquidacion->vacaciones($desvinculacion);

        if ($calculo['remuneracion'] === null) {
            throw new ReglaNegocioException('El vínculo no tiene la remuneración guardada: sin ella no hay con qué valorar los días.');
        }

        $desvinculacion->update([
            'vacaciones_dias'           => $calculo['dias'],
            'vacaciones_remuneracion'   => $calculo['remuneracion'],
            'vacaciones_monto'          => $calculo['monto'],
            'vacaciones_detalle'        => $calculo,
            'vacaciones_confirmada_por' => $usuario->id,
            'vacaciones_confirmada_en'  => now(),
        ]);

        return $this->detalle($desvinculacion->fresh());
    }

    /**
     * El Director registra la indemnización o compensación, dentro del tope
     * del Mandato 2. Desde ese momento, quien la cobró no reingresa mientras
     * no la devuelva.
     *
     * @param  array{concepto: string, monto: float|string, resolucion: string}  $datos
     */
    public function registrarIndemnizacion(Desvinculacion $desvinculacion, array $datos, User $usuario): array
    {
        $this->exigirDirector($usuario, 'La indemnización la registra el Director de Talento Humano: es dinero que va a Financiero.');
        $this->exigirAbierta($desvinculacion);

        if ($desvinculacion->indemnizacion_registrada_en !== null) {
            throw new ReglaNegocioException('La indemnización ya se registró.');
        }

        $tope = $this->liquidacion->topeDeLaIndemnizacion($desvinculacion);
        $monto = round((float) $datos['monto'], 2);

        if ($tope['tope'] === null) {
            throw new ReglaNegocioException('Sin el salario básico del año de la salida no se calcula el tope del Mandato 2: cárguelo en «Contrato colectivo».');
        }

        if ($monto > $tope['tope']) {
            throw new ReglaNegocioException('Pasa el tope del Mandato Constituyente 2: '.LiquidacionDeLaSalida::SALARIOS_POR_ANIO
                .' salarios básicos por año de servicio, hasta '.LiquidacionDeLaSalida::SALARIOS_MAXIMOS.': $'
                .number_format($tope['tope'], 2, ',', '.').' para '.number_format((float) $tope['anios'], 2, ',', '.').' años.');
        }

        $desvinculacion->update([
            'indemnizacion_concepto'       => $datos['concepto'],
            'indemnizacion_monto'          => $monto,
            'indemnizacion_resolucion'     => trim($datos['resolucion']),
            'indemnizacion_tope'           => $tope['tope'],
            'indemnizacion_registrada_por' => $usuario->id,
            'indemnizacion_registrada_en'  => now(),
        ]);

        return $this->detalle($desvinculacion->fresh());
    }

    /** Devolvió la indemnización: ya puede reingresar (Mandato 2, art. 8). */
    public function registrarDevolucion(Desvinculacion $desvinculacion, string $fecha, string $documento, User $usuario): array
    {
        if (! $desvinculacion->prohibeElReingreso()) {
            throw new ReglaNegocioException('No hay una indemnización cobrada sin devolver en esta salida.');
        }

        $desvinculacion->update([
            'indemnizacion_devuelta_en'               => $fecha,
            'indemnizacion_devolucion_documento'      => trim($documento),
            'indemnizacion_devolucion_registrada_por' => $usuario->id,
        ]);

        return $this->detalle($desvinculacion->fresh());
    }

    /**
     * Quien cobró una indemnización al salir y no la devolvió no reingresa
     * (Mandato Constituyente 2, art. 8). Lo llama el registro del ingreso; la
     * restitución que ordena un juez y el período de una autoridad electa no
     * pasan por aquí.
     */
    public function impedirReingresoProhibido(int $servidorId): void
    {
        $salida = Desvinculacion::where('servidor_id', $servidorId)
            ->where('estado', '!=', Desvinculacion::CANCELADA)
            ->whereNotNull('indemnizacion_registrada_en')
            ->where('indemnizacion_monto', '>', 0)
            ->whereNull('indemnizacion_devuelta_en')
            ->orderByDesc('fecha_salida')
            ->first();

        if ($salida) {
            throw new ReglaNegocioException('Cobró una indemnización al salir el '.$salida->fecha_salida->format('d/m/Y')
                .' ('.(self::CONCEPTOS_DE_INDEMNIZACION[$salida->indemnizacion_concepto] ?? 'indemnización').'): no puede reingresar mientras no la devuelva '
                .'(Mandato Constituyente 2, art. 8). Si ya la devolvió, regístrelo en su desvinculación.');
        }
    }

    /**
     * La devolución de un bien que sigue a cargo de quien sale. La hace
     * Tecnologías de la Información; mientras el Inventario TI no tenga su
     * pantalla, Talento Humano la registra aquí con el acta.
     */
    public function devolverBien(Desvinculacion $desvinculacion, int $asignacionId, string $fecha, ?string $observaciones): array
    {
        $asignacion = AsignacionBien::where('servidor_id', $desvinculacion->servidor_id)->find($asignacionId);

        if (! $asignacion) {
            throw new ReglaNegocioException('Ese bien no está a cargo de esta persona.');
        }

        $this->custodia->devolver($asignacion, $fecha, $observaciones);

        return $this->detalle($desvinculacion->fresh());
    }

    // ── Los pasos ───────────────────────────────────────────────────

    /** Abierta mientras quede un paso pendiente; cerrada cuando no. */
    private function actualizarEstado(Desvinculacion $desvinculacion): Desvinculacion
    {
        if ($desvinculacion->estado === Desvinculacion::CANCELADA) {
            return $desvinculacion;
        }

        $pendientes = collect($this->pasos($desvinculacion))->where('estado', DesvinculacionPaso::PENDIENTE)->count();
        $estado = $pendientes === 0 ? Desvinculacion::CERRADA : Desvinculacion::ABIERTA;

        if ($estado !== $desvinculacion->estado) {
            $desvinculacion->update([
                'estado'     => $estado,
                'cerrada_en' => $estado === Desvinculacion::CERRADA ? now() : null,
            ]);
        }

        return $desvinculacion;
    }

    /** @return list<array<string, mixed>> */
    private function pasos(Desvinculacion $desvinculacion): array
    {
        $desvinculacion->loadMissing(['pasos.registradoPor.servidor:id,nombre,apellido', 'contrato', 'movimientoCierre.actaFiniquito']);
        $decididos = $desvinculacion->pasos->keyBy(fn (DesvinculacionPaso $p) => $p->paso->value);

        return collect(PasoDesvinculacion::cases())->map(function (PasoDesvinculacion $paso) use ($desvinculacion, $decididos) {
            $decidido = $decididos->get($paso->value);
            [$delDato, $detalle, $extra] = $this->segunElDato($paso, $desvinculacion, $decididos);

            // Lo que no aplica, por decisión; si no, lo que dice el dato.
            $estado = $decidido?->estado === DesvinculacionPaso::NO_APLICA ? DesvinculacionPaso::NO_APLICA : $delDato;

            $quien = $decidido?->registradoPor;

            return [
                'paso'                 => $paso->value,
                'etiqueta'             => $paso->etiqueta(),
                'responsable'          => $paso->responsable(),
                'estado'               => $estado,
                'se_marca'             => $paso->seMarca(),
                'lo_marca_el_director' => $paso->loMarcaElDirector(),
                'detalle'              => $detalle,
                'nota'                 => $decidido?->nota,
                'registrado_por'       => $quien?->servidor ? trim("{$quien->servidor->nombre} {$quien->servidor->apellido}") : $quien?->usuario_ti,
                'registrado_en'        => $decidido?->registrado_en?->toIso8601String(),
                ...$extra,
            ];
        })->values()->all();
    }

    /**
     * Lo que dice el dato del paso: su estado, el texto para la pantalla y lo
     * que lo acompaña.
     *
     * @param  \Illuminate\Support\Collection<string, DesvinculacionPaso>  $decididos
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function segunElDato(PasoDesvinculacion $paso, Desvinculacion $desvinculacion, Collection $decididos): array
    {
        $servidorId = $desvinculacion->servidor_id;
        $hecho = DesvinculacionPaso::HECHO;
        $pendiente = DesvinculacionPaso::PENDIENTE;

        return match ($paso) {
            PasoDesvinculacion::BIENES => $this->bienes($servidorId),

            PasoDesvinculacion::CREDENCIAL => $this->credencial($servidorId),

            PasoDesvinculacion::USUARIO => ($usuario = User::where('servidor_id', $servidorId)->where('activo', true)->first())
                ? [$pendiente, "El usuario {$usuario->usuario_ti} sigue activo: lo da de baja Tecnologías de la Información, en Usuarios.", []]
                : [$hecho, 'Ya no tiene un usuario activo.', []],

            PasoDesvinculacion::CERTIFICADO => ($emision = EmisionCertificadoLaboral::where('servidor_id', $servidorId)
                ->whereDate('emitido_en', '>=', $desvinculacion->fecha_salida->toDateString())
                ->orderByDesc('emitido_en')
                ->first())
                ? [$hecho, "Se emitió el {$emision->codigo} el {$emision->emitido_en->format('d/m/Y')}.", []]
                : [$pendiente, 'Se emite desde el expediente, después de la salida.', []],

            PasoDesvinculacion::DECLARACION => ($declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
                ->where('tipo_declaracion', TipoDeclaracion::FIN_GESTION->value)
                ->whereDate('fecha_declaracion', '>=', $desvinculacion->contrato?->fecha_inicio?->toDateString() ?? $desvinculacion->fecha_salida->toDateString())
                ->orderByDesc('fecha_declaracion')
                ->first())
                ? [$hecho, "Registrada la del {$declaracion->fecha_declaracion->format('d/m/Y')}.", []]
                : [$pendiente, 'La presenta ante la Contraloría al terminar sus funciones, y se registra en el expediente.', []],

            PasoDesvinculacion::VACACIONES => $this->vacaciones($desvinculacion),

            PasoDesvinculacion::INDEMNIZACION => $this->indemnizacion($desvinculacion),

            PasoDesvinculacion::REINGRESO => $this->reingreso($desvinculacion, $decididos),
        };
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>} */
    private function vacaciones(Desvinculacion $desvinculacion): array
    {
        if ($desvinculacion->vacaciones_confirmada_en !== null) {
            return [DesvinculacionPaso::HECHO,
                'Confirmada: '.$this->numero((float) $desvinculacion->vacaciones_dias).' días, $'.$this->numero((float) $desvinculacion->vacaciones_monto)
                    .' de referencia. Llega a Financiero en las novedades del mes de la salida.',
                ['liquidacion' => $desvinculacion->vacaciones_detalle, 'confirmada' => true]];
        }

        $calculo = $this->liquidacion->vacaciones($desvinculacion);

        return [DesvinculacionPaso::PENDIENTE,
            'Calculada: '.$this->numero($calculo['dias']).' días'
                .($calculo['monto'] !== null ? ', $'.$this->numero($calculo['monto']).' de referencia' : ', sin la remuneración del vínculo')
                .'. La confirma el Director.',
            ['liquidacion' => $calculo, 'confirmada' => false]];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>} */
    private function indemnizacion(Desvinculacion $desvinculacion): array
    {
        $tope = $this->liquidacion->topeDeLaIndemnizacion($desvinculacion);
        // Al obrero se le propone el total de su liquidación referencial (4.5).
        $sugerido = $desvinculacion->movimientoCierre
            ? (app(TerminacionObreroService::class)->liquidacion($desvinculacion->movimientoCierre)['total'] ?? null)
            : null;

        $extra = ['indemnizacion' => [
            'conceptos'      => self::CONCEPTOS_DE_INDEMNIZACION,
            'salario_basico' => $tope['salario_basico'],
            'anios'          => $tope['anios'],
            'tope'           => $tope['tope'],
            'sugerido'       => $sugerido,
            'concepto'       => $desvinculacion->indemnizacion_concepto,
            'monto'          => $desvinculacion->indemnizacion_monto !== null ? (float) $desvinculacion->indemnizacion_monto : null,
            'resolucion'     => $desvinculacion->indemnizacion_resolucion,
        ]];

        if ($desvinculacion->indemnizacion_registrada_en !== null) {
            return [DesvinculacionPaso::HECHO,
                (self::CONCEPTOS_DE_INDEMNIZACION[$desvinculacion->indemnizacion_concepto] ?? 'Indemnización').': $'
                    .$this->numero((float) $desvinculacion->indemnizacion_monto).', '.$desvinculacion->indemnizacion_resolucion.'.',
                $extra];
        }

        return [DesvinculacionPaso::PENDIENTE,
            'Solo si corresponde —jubilación, retiro voluntario, supresión, despido—, hasta el tope del Mandato 2: '
                .($tope['tope'] !== null ? '$'.$this->numero($tope['tope']).'.' : 'falta el salario básico del año de la salida.'),
            $extra];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, DesvinculacionPaso>  $decididos
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function reingreso(Desvinculacion $desvinculacion, Collection $decididos): array
    {
        $extra = ['reingreso' => [
            'prohibido'  => $desvinculacion->prohibeElReingreso(),
            'devuelta_en' => $desvinculacion->indemnizacion_devuelta_en?->toDateString(),
            'documento'  => $desvinculacion->indemnizacion_devolucion_documento,
        ]];

        if ($desvinculacion->indemnizacion_registrada_en !== null && (float) $desvinculacion->indemnizacion_monto > 0) {
            return [DesvinculacionPaso::HECHO, $desvinculacion->indemnizacion_devuelta_en
                ? 'Devolvió la indemnización el '.$desvinculacion->indemnizacion_devuelta_en->format('d/m/Y').': puede reingresar.'
                : 'Cobró una indemnización: no reingresa al sector público mientras no la devuelva (Mandato Constituyente 2, art. 8).',
                $extra];
        }

        if ($decididos->get(PasoDesvinculacion::INDEMNIZACION->value)?->estado === DesvinculacionPaso::NO_APLICA) {
            return [DesvinculacionPaso::NO_APLICA, 'No cobró indemnización: puede reingresar.', $extra];
        }

        return [DesvinculacionPaso::PENDIENTE, 'Depende de la indemnización: si la cobra, no reingresa mientras no la devuelva.', $extra];
    }

    private function exigirDirector(User $usuario, string $mensaje): void
    {
        if (! $usuario->can('registrar-accion-personal')) {
            throw new ReglaNegocioException($mensaje);
        }
    }

    private function exigirAbierta(Desvinculacion $desvinculacion): void
    {
        if ($desvinculacion->estado === Desvinculacion::CANCELADA) {
            throw new ReglaNegocioException('La desvinculación se canceló: el cese se anuló y el vínculo volvió.');
        }
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    /** @return array{0: string, 1: string, 2: array{bienes: list<array<string, mixed>>, prestamos: list<array<string, mixed>>}} */
    private function bienes(int $servidorId): array
    {
        $asignaciones = AsignacionBien::where('servidor_id', $servidorId)
            ->where('estado', 'activa')
            ->whereNull('fecha_devolucion')
            ->with('bien.tipo:id,nombre', 'bien.marca:id,nombre')
            ->orderBy('id')
            ->get();

        $bienes = $asignaciones->map(fn (AsignacionBien $a) => [
            'asignacion_id'    => $a->id,
            'bien'             => trim(collect([$a->bien?->tipo?->nombre, $a->bien?->marca?->nombre, $a->bien?->modelo])->filter()->implode(' ')) ?: 'Bien',
            'codigo'           => $a->bien?->codigo_institucional ?? $a->bien?->codigo_qr,
            'numero_serie'     => $a->bien?->numero_serie,
            'fecha_asignacion' => $a->fecha_asignacion?->toDateString(),
        ])->values()->all();

        // Los equipos de préstamo que se llevó y no han vuelto (TIC, fase 2.4):
        // no son custodia, pero también tiene que devolverlos antes de salir.
        $prestamos = PrestamoEquipo::where('servidor_id', $servidorId)
            ->where('estado', EstadoPrestamo::EN_CURSO)
            ->with('items.bien:id,codigo_institucional')
            ->orderBy('fin_previsto')
            ->get()
            ->map(fn (PrestamoEquipo $p) => [
                'prestamo_id'  => $p->id,
                'folio'        => $p->folio,
                'equipos'      => $p->items->map(fn ($i) => $i->bien?->codigo_institucional)->filter()->implode(', '),
                'fin_previsto' => $p->fin_previsto->toIso8601String(),
                'vencido'      => $p->vencido(),
            ])->values()->all();

        $pendientes = array_filter([
            match ($asignaciones->count()) { 0 => null, 1 => 'Tiene 1 bien a su cargo', default => "Tiene {$asignaciones->count()} bienes a su cargo" },
            match (count($prestamos)) { 0 => null, 1 => 'tiene 1 préstamo de TIC sin devolver', default => 'tiene ' . count($prestamos) . ' préstamos de TIC sin devolver' },
        ]);

        return $pendientes === []
            ? [DesvinculacionPaso::HECHO, 'No tiene bienes a su cargo en el Inventario TI ni equipos prestados.', ['bienes' => [], 'prestamos' => []]]
            : [DesvinculacionPaso::PENDIENTE, ucfirst(implode(' y ', $pendientes)) . '.', ['bienes' => $bienes, 'prestamos' => $prestamos]];
    }

    /**
     * La tarjeta de la última credencial, la que se anuló al salir. Las
     * anteriores ya no validan y se reemplazaron en su momento: lo que importa
     * es la que el servidor llevaba. Si nunca se imprimió, o se perdió, no hay
     * nada que recoger.
     *
     * @return array{0: string, 1: string, 2: array{credencial_id: ?int}}
     */
    private function credencial(int $servidorId): array
    {
        $ultima = Credencial::where('servidor_id', $servidorId)
            ->with('ultimaImpresion')
            ->orderByDesc('id')
            ->first();
        $impresion = $ultima?->ultimaImpresion;
        $extra = ['credencial_id' => $ultima?->id];

        return match (true) {
            $impresion === null => [DesvinculacionPaso::HECHO, 'No se le imprimió una credencial: no hay tarjeta que devolver.', $extra],

            $ultima->devuelta_en !== null => [DesvinculacionPaso::HECHO, "Entregó la tarjeta el {$ultima->devuelta_en->format('d/m/Y')}.", $extra],

            $ultima->estado === EstadoCredencial::REVOCADA && ! $ultima->motivo_revocacion?->tarjetaRecuperable()
                => [DesvinculacionPaso::HECHO, 'La tarjeta se reportó por '.mb_strtolower($ultima->motivo_revocacion->etiqueta()).' y su código ya no valida: no hay nada que recoger.', $extra],

            default => [DesvinculacionPaso::PENDIENTE, "Tiene la tarjeta impresa el {$impresion->created_at->format('d/m/Y')}: la recibe Comunicación Social, en Credenciales.", $extra],
        };
    }

    private function resumen(Desvinculacion $desvinculacion): array
    {
        $desvinculacion->loadMissing(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'movimientoCierre', 'contrato:id,tipo_nombramiento,fecha_inicio']);
        $pasos = collect($this->pasos($desvinculacion));
        $cierre = $desvinculacion->movimientoCierre;

        return [
            'id'                => $desvinculacion->id,
            'servidor_id'       => $desvinculacion->servidor_id,
            'servidor'          => OcupantesDelPuesto::nombre($desvinculacion->servidor),
            'cedula'            => $desvinculacion->servidor?->cedula,
            'fecha_salida'      => $desvinculacion->fecha_salida->toDateString(),
            'causal'            => $cierre?->causal()?->etiqueta() ?? $cierre?->etiqueta(),
            'acto'              => $cierre?->codigo_registro,
            'movimiento_id'     => $cierre?->id,
            'tipo_nombramiento' => $desvinculacion->contrato?->tipo_nombramiento?->etiqueta(),
            'estado'            => $desvinculacion->estado,
            'cerrada_en'        => $desvinculacion->cerrada_en?->toIso8601String(),
            'cancelada_en'      => $desvinculacion->cancelada_en?->toIso8601String(),
            'pasos_hechos'      => $pasos->whereIn('estado', [DesvinculacionPaso::HECHO, DesvinculacionPaso::NO_APLICA])->count(),
            'pasos_total'       => $pasos->count(),
        ];
    }
}
