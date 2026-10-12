<?php

namespace App\Services\Reporte;

use App\Contracts\Reporte\ReporteSiithSutServiceInterface;
use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use App\Models\Reporte\TranscripcionReporte;
use App\Models\User;
use App\Services\Expediente\OcupantesDelPuesto;
use App\Services\Nomina\MesDeNomina;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El reporte de apoyo a la transcripción al SIITH y al SUT (diseño de Acciones
 * de Personal, 9.12; fase 7.5).
 *
 * Hasta aquí se configuraba por tipo de movimiento, con la taxonomía antigua,
 * y recorría la línea de tiempo de cada servidor para separar los regímenes.
 * Ahora:
 *
 * - se configura por clase;
 * - el portal lo decide el instrumento del acto: la acción de personal, el
 *   registro del contrato y el acto de autoridad van al SIITH; la hoja de
 *   registro interno del obrero, al SUT; el contrato civil, a ninguno;
 * - entra lo registrado en el mes, por la fecha de registro —con la fecha en
 *   que rige—, y lo que no se transcribió sigue pendiente en los meses
 *   siguientes. El acto anulado después de transcribirse vuelve a aparecer,
 *   para anularlo también en el portal.
 */
class ReporteSiithSutService implements ReporteSiithSutServiceInterface
{
    /** @var array<string, list<InstrumentoActo>> */
    private const INSTRUMENTOS = [
        'siith' => [InstrumentoActo::ACCION_PERSONAL, InstrumentoActo::REGISTRO_CONTRATO, InstrumentoActo::ACTO_AUTORIDAD],
        'sut'   => [InstrumentoActo::DOCUMENTO_CT],
    ];

    private const EMITIDAS = [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA];

    public function reporte(string $portal, string $periodo, string $situacion): array
    {
        $mes = MesDeNomina::de($periodo);
        $filas = $this->filas($portal, $mes, $situacion);

        return [
            'portal'     => $portal,
            'periodo'    => $mes->periodo,
            'etiqueta'   => $mes->etiqueta(),
            'situacion'  => $situacion,
            'actos'      => $filas->all(),
            'resumen'    => [
                'total'                => $filas->count(),
                'pendientes'           => $filas->where('transcrito', false)->count(),
                'anulaciones_pendientes' => $filas->where('anulacion_pendiente', true)->count(),
            ],
        ];
    }

    public function filasDelExcel(string $portal, string $periodo, string $situacion): Collection
    {
        return $this->filas($portal, MesDeNomina::de($periodo), $situacion)->map(fn (array $f) => [
            'NÚMERO'                  => $f['numero'],
            'SITUACIÓN'               => $f['anulacion_pendiente'] ? 'Anulada: anular en el portal' : ($f['transcrito'] ? 'Transcrito' : 'Pendiente'),
            'FECHA DE REGISTRO'       => $f['fecha_registro'],
            'RIGE DESDE'              => $f['fecha_efectiva'],
            'CLASE'                   => $f['clase'],
            'CAUSAL'                  => $f['causal'],
            'CÉDULA'                  => $f['cedula'],
            'SERVIDOR'                => $f['servidor'],
            'MODALIDAD'               => $f['modalidad'],
            'PUESTO DE ORIGEN'        => $f['origen']['puesto'],
            'GRADO DE ORIGEN'         => $f['origen']['grado'],
            'UNIDAD DE ORIGEN'        => $f['origen']['unidad'],
            'REMUNERACIÓN DE ORIGEN'  => $f['origen']['remuneracion'],
            'PUESTO DE DESTINO'       => $f['destino']['puesto'],
            'GRADO DE DESTINO'        => $f['destino']['grado'],
            'UNIDAD DE DESTINO'       => $f['destino']['unidad'],
            'REMUNERACIÓN DE DESTINO' => $f['destino']['remuneracion'],
            'BASE LEGAL'              => $f['base_legal'],
            'DOCUMENTO'               => $f['documento'],
        ]);
    }

    public function transcribir(string $portal, array $movimientoIds, User $usuario): int
    {
        $this->exigirPortal($portal);

        return DB::transaction(function () use ($portal, $movimientoIds, $usuario) {
            $actos = MovimientoPersonal::whereIn('id', $movimientoIds)->get()->keyBy('id');
            $hechas = 0;

            foreach ($movimientoIds as $id) {
                $acto = $actos->get($id);

                if (! $acto || ! $this->vaAlPortal($acto, $portal)) {
                    throw new ReglaNegocioException('Un acto de la lista no se reporta a '.strtoupper($portal).'.');
                }

                $transcripcion = TranscripcionReporte::where('movimiento_id', $id)->where('portal', $portal)->first();

                if ($acto->estado === EstadoAccionPersonal::ANULADA) {
                    // Lo anulado solo vuelve si ya se había transcrito: es su anulación.
                    if ($transcripcion && $transcripcion->anulacion_transcrita_en === null) {
                        $transcripcion->update(['anulacion_transcrita_por' => $usuario->id, 'anulacion_transcrita_en' => now()]);
                        $hechas++;
                    }

                    continue;
                }

                if (! $transcripcion && in_array($acto->estado, self::EMITIDAS, true)) {
                    TranscripcionReporte::create([
                        'movimiento_id'  => $id,
                        'portal'         => $portal,
                        'transcrito_por' => $usuario->id,
                        'transcrito_en'  => now(),
                    ]);
                    $hechas++;
                }
            }

            return $hechas;
        });
    }

    public function configuracion(): array
    {
        $marcas = ConfiguracionReporteMovimiento::whereNotNull('clase')->with('actualizadoPor:id,usuario_ti')->get()
            ->keyBy(fn (ConfiguracionReporteMovimiento $c) => $c->clase->value);

        return collect(ClaseAccionPersonal::cases())->map(fn (ClaseAccionPersonal $clase) => [
            'clase'            => $clase->value,
            'etiqueta'         => $clase->etiqueta(),
            'familia'          => $clase->familia()->etiqueta(),
            'reportable_siith' => (bool) $marcas->get($clase->value)?->reportable_siith,
            'reportable_sut'   => (bool) $marcas->get($clase->value)?->reportable_sut,
            'actualizado_por'  => $marcas->get($clase->value)?->actualizadoPor?->usuario_ti,
            'actualizado_en'   => $marcas->get($clase->value)?->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    public function actualizarConfiguracion(ClaseAccionPersonal $clase, array $marcas, User $usuario): array
    {
        ConfiguracionReporteMovimiento::updateOrCreate(['clase' => $clase->value], [
            ...array_intersect_key($marcas, array_flip(['reportable_siith', 'reportable_sut'])),
            'actualizado_por' => $usuario->id,
        ]);

        return $this->configuracion();
    }

    // ── Las filas ───────────────────────────────────────────────────

    /** @return Collection<int, array<string, mixed>> */
    private function filas(string $portal, MesDeNomina $mes, string $situacion): Collection
    {
        $this->exigirPortal($portal);

        $columna = $portal === 'siith' ? 'reportable_siith' : 'reportable_sut';
        $clases = ConfiguracionReporteMovimiento::whereNotNull('clase')->where($columna, true)->pluck('clase')
            ->map(fn ($c) => $c instanceof ClaseAccionPersonal ? $c->value : $c)
            ->all();

        if ($clases === []) {
            return collect();
        }

        $instrumentos = array_map(fn (InstrumentoActo $i) => $i->value, self::INSTRUMENTOS[$portal]);
        $inicio = $mes->inicio->toDateString();
        $fin = $mes->fin->toDateString();

        // Sin tipo: lo usan whereHas (un Builder) y la carga ansiosa (la relación).
        $transcrito = fn ($q) => $q->where('portal', $portal);

        $actos = MovimientoPersonal::query()
            ->whereIn('clase', $clases)
            ->where(function (Builder $q) use ($instrumentos, $portal) {
                $fueraDelSiith = [TipoNombramiento::SERVICIOS_PROFESIONALES->value, TipoNombramiento::CODIGO_TRABAJO->value];

                if ($portal === 'siith') {
                    // Los actos de antes de la columna quedaron como acciones de
                    // personal: el ingreso del contrato civil o del obrero lo dice
                    // su modalidad, y no va al SIITH.
                    $q->where(fn (Builder $i) => $i->whereIn('instrumento', $instrumentos)->orWhereNull('instrumento'))
                        ->where(fn (Builder $m) => $m->whereNull('tipo_nombramiento_propuesto')
                            ->orWhereNotIn('tipo_nombramiento_propuesto', $fueraDelSiith));
                } else {
                    $q->whereIn('instrumento', $instrumentos)
                        ->orWhere('tipo_nombramiento_propuesto', TipoNombramiento::CODIGO_TRABAJO->value);
                }
            })
            ->whereNotNull('codigo_registro')
            ->where(function (Builder $q) use ($situacion, $transcrito, $inicio, $fin, $portal) {
                // Lo emitido: el del mes o, en los pendientes, también el de
                // antes que sigue sin transcribirse.
                $q->where(fn (Builder $e) => $e->whereIn('estado', array_map(fn ($s) => $s->value, self::EMITIDAS))
                    ->when(
                        $situacion === 'pendientes',
                        fn (Builder $p) => $p->whereDate('fecha_registro', '<=', $fin)->whereDoesntHave('transcripcionesReporte', $transcrito),
                        fn (Builder $p) => $p->whereBetween('fecha_registro', [$inicio, $fin])
                            ->when($situacion === 'transcritos', fn (Builder $t) => $t->whereHas('transcripcionesReporte', $transcrito)),
                    ));

                // Y lo anulado después de transcribirse, cuya anulación falta.
                if ($situacion !== 'transcritos') {
                    $q->orWhere(fn (Builder $a) => $a->where('estado', EstadoAccionPersonal::ANULADA->value)
                        ->whereHas('transcripcionesReporte', fn (Builder $t) => $t->where('portal', $portal)->whereNull('anulacion_transcrita_en')));
                }
            })
            ->with([
                'servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido',
                'servidor.contratoVigente',
                'puestoOrigen.cargo:id,nombre', 'puestoDestino.cargo:id,nombre',
                'unidadOrigen:id,nombre', 'unidadDestino:id,nombre',
                'grupoOcupacionalOrigen:id,grado_codigo', 'grupoOcupacionalDestino:id,grado_codigo',
                'transcripcionesReporte' => $transcrito,
                'lote:id,documento_origen',
            ])
            ->orderBy('fecha_registro')
            ->orderBy('id')
            ->get();

        return $actos->map(fn (MovimientoPersonal $m) => $this->fila($m))->values();
    }

    private function fila(MovimientoPersonal $m): array
    {
        $transcripcion = $m->transcripcionesReporte->first();
        $anulada = $m->estado === EstadoAccionPersonal::ANULADA;
        $dinero = fn ($v) => $v !== null ? (float) $v : null;

        return [
            'movimiento_id'       => $m->id,
            'numero'              => $m->codigo_registro,
            'estado'              => $m->estado->value,
            'fecha_registro'      => $m->fecha_registro?->toDateString(),
            'fecha_efectiva'      => $m->fecha_efectiva?->toDateString(),
            'clase'               => $m->clase?->etiqueta(),
            'causal'              => $m->causal()?->etiqueta(),
            'cedula'              => $m->servidor?->cedula,
            'servidor'            => OcupantesDelPuesto::nombre($m->servidor),
            'modalidad'           => ($m->tipo_nombramiento_propuesto ?? $m->servidor?->contratoVigente?->tipo_nombramiento)?->etiqueta(),
            'origen'              => [
                'puesto'       => $m->puestoOrigen?->cargo?->nombre,
                'grado'        => $m->grupoOcupacionalOrigen?->grado_codigo,
                'unidad'       => $m->unidadOrigen?->nombre,
                'remuneracion' => $dinero($m->remuneracion_origen),
            ],
            'destino'             => [
                'puesto'       => $m->puestoDestino?->cargo?->nombre,
                'grado'        => $m->grupoOcupacionalDestino?->grado_codigo,
                'unidad'       => $m->unidadDestino?->nombre,
                'remuneracion' => $dinero($m->remuneracion_propuesta),
            ],
            'base_legal'          => $m->baseLegalImpresa(),
            'documento'           => $m->resolucion_numero ?? $m->documento_respaldo ?? $m->lote?->documento_origen,
            'transcrito'          => $transcripcion !== null,
            'transcrito_en'       => $transcripcion?->transcrito_en?->toIso8601String(),
            'anulacion_pendiente' => $anulada && $transcripcion !== null && $transcripcion->anulacion_transcrita_en === null,
        ];
    }

    /** Lo mismo que la consulta de `filas()`, para un acto. */
    private function vaAlPortal(MovimientoPersonal $acto, string $portal): bool
    {
        $modalidad = $acto->tipo_nombramiento_propuesto;
        $instrumento = $acto->instrumento ?? InstrumentoActo::ACCION_PERSONAL;

        return $portal === 'siith'
            ? in_array($instrumento, self::INSTRUMENTOS['siith'], true)
                && ! in_array($modalidad, [TipoNombramiento::SERVICIOS_PROFESIONALES, TipoNombramiento::CODIGO_TRABAJO], true)
            : $instrumento === InstrumentoActo::DOCUMENTO_CT || $modalidad === TipoNombramiento::CODIGO_TRABAJO;
    }

    private function exigirPortal(string $portal): void
    {
        if (! isset(self::INSTRUMENTOS[$portal])) {
            throw new ReglaNegocioException("Portal '{$portal}' no reconocido. Use 'siith' o 'sut'.");
        }
    }
}
