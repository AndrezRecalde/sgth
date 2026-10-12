<?php

namespace App\Services\Expediente;

use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Enums\EstadoContrato;
use App\Enums\PasoDesvinculacion;
use App\Enums\TipoDeclaracion;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\DesvinculacionPaso;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\InventarioTi\AsignacionBien;
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
 * gestión. La liquidación de vacaciones y la indemnización las marca el
 * Director hasta que tengan su cálculo (7.4b). Todo paso puede no aplicar,
 * con su motivo; lo que no le toca al contrato civil no aplica desde que nace.
 *
 * Se cierra sola cuando cada paso está hecho o no aplica, y se cancela si el
 * cese se anula.
 */
class DesvinculacionService
{
    public function __construct(
        private readonly InventarioTiServiceInterface $inventario,
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

        if ($estado === DesvinculacionPaso::HECHO && ! $paso->seMarca()) {
            throw new ReglaNegocioException("«{$paso->etiqueta()}» queda hecho solo, cuando lo registra su módulo. Si no corresponde, márquelo como que no aplica.");
        }

        if ($paso->loMarcaElDirector() && $estado !== DesvinculacionPaso::PENDIENTE && ! $usuario->can('registrar-accion-personal')) {
            throw new ReglaNegocioException("«{$paso->etiqueta()}» lo marca el Director de Talento Humano: es dinero que va a Financiero.");
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

        $this->inventario->devolverBien($asignacion, $fecha, $observaciones);

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
            [$delDato, $detalle, $extra] = $this->segunElDato($paso, $desvinculacion);

            $estado = match (true) {
                $decidido?->estado === DesvinculacionPaso::NO_APLICA => DesvinculacionPaso::NO_APLICA,
                $delDato === true                                    => DesvinculacionPaso::HECHO,
                $paso->seMarca() && $decidido?->estado === DesvinculacionPaso::HECHO => DesvinculacionPaso::HECHO,
                default                                              => DesvinculacionPaso::PENDIENTE,
            };

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
     * Lo que dice el dato del paso: si ya está hecho (null si no se lee de
     * ningún lado), el texto para la pantalla y lo que la acompaña.
     *
     * @return array{0: ?bool, 1: string, 2: array<string, mixed>}
     */
    private function segunElDato(PasoDesvinculacion $paso, Desvinculacion $desvinculacion): array
    {
        $servidorId = $desvinculacion->servidor_id;

        return match ($paso) {
            PasoDesvinculacion::BIENES => $this->bienes($servidorId),

            PasoDesvinculacion::USUARIO => ($usuario = User::where('servidor_id', $servidorId)->where('activo', true)->first())
                ? [false, "El usuario {$usuario->usuario_ti} sigue activo: lo da de baja Tecnologías de la Información, en Usuarios.", []]
                : [true, 'Ya no tiene un usuario activo.', []],

            PasoDesvinculacion::CERTIFICADO => ($emision = EmisionCertificadoLaboral::where('servidor_id', $servidorId)
                ->whereDate('emitido_en', '>=', $desvinculacion->fecha_salida->toDateString())
                ->orderByDesc('emitido_en')
                ->first())
                ? [true, "Se emitió el {$emision->codigo} el {$emision->emitido_en->format('d/m/Y')}.", []]
                : [false, 'Se emite desde el expediente, después de la salida.', []],

            PasoDesvinculacion::DECLARACION => ($declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
                ->where('tipo_declaracion', TipoDeclaracion::FIN_GESTION->value)
                ->whereDate('fecha_declaracion', '>=', $desvinculacion->contrato?->fecha_inicio?->toDateString() ?? $desvinculacion->fecha_salida->toDateString())
                ->orderByDesc('fecha_declaracion')
                ->first())
                ? [true, "Registrada la del {$declaracion->fecha_declaracion->format('d/m/Y')}.", []]
                : [false, 'La presenta ante la Contraloría al terminar sus funciones, y se registra en el expediente.', []],

            PasoDesvinculacion::VACACIONES => $desvinculacion->movimientoCierre?->actaFiniquito
                ? [true, 'El acta de finiquito de la terminación ya se registró: liquida lo que el Código del Trabajo le debe.', []]
                : [null, 'Las vacaciones no gozadas y lo que se le debe del último mes. Hasta que el sistema la calcule, se marca cuando Financiero la liquide.', []],

            PasoDesvinculacion::INDEMNIZACION => [null,
                'Solo si corresponde —jubilación, retiro voluntario, supresión, despido—, dentro de los topes del Mandato 2: 7 salarios básicos por año, hasta 210.', []],
        };
    }

    /** @return array{0: bool, 1: string, 2: array{bienes: list<array<string, mixed>>}} */
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

        return $asignaciones->isEmpty()
            ? [true, 'No tiene bienes a su cargo en el Inventario TI.', ['bienes' => []]]
            : [false, $asignaciones->count() === 1 ? 'Tiene 1 bien a su cargo.' : "Tiene {$asignaciones->count()} bienes a su cargo.", ['bienes' => $bienes]];
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
